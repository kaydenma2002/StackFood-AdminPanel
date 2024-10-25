<?php

namespace App\CentralLogics;

use App\Models\Restaurant;
use App\Models\OrderTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RestaurantLogic
{
    public static function get_restaurants(array $additional_data)
{

    // Fetch sort and filter configurations
    $all_restaurant_default_status = \App\Models\BusinessSetting::where('key', 'all_restaurant_default_status')->value('value') ?? 1;
    $all_restaurant_sort_by_general = \App\Models\PriorityList::where('name', 'all_restaurant_sort_by_general')->where('type', 'general')->value('value') ?? '';
    $all_restaurant_sort_by_unavailable = \App\Models\PriorityList::where('name', 'all_restaurant_sort_by_unavailable')->where('type', 'unavailable')->value('value') ?? '';
    $all_restaurant_sort_by_temp_closed = \App\Models\PriorityList::where('name', 'all_restaurant_sort_by_temp_closed')->where('type', 'temp_closed')->value('value') ?? '';

    // Build the search query using Meilisearch
    $searchQuery = Restaurant::search($additional_data['name'] ?? '');

    // Apply filters
    $searchQuery->whereIn('zone_id', $additional_data['zone_id'] ?? []);

    if ($additional_data['filter'] === 'delivery') {
        $searchQuery->where('delivery', 1);
    }

    if ($additional_data['filter'] === 'take_away') {
        $searchQuery->where('takeaway', 1);
    }

    if ($additional_data['avg_rating'] > 0) {
        $searchQuery->where('avg_rating', '>=', $additional_data['avg_rating']);
    }

    if (isset($additional_data['veg']) && $additional_data['veg'] == 1) {
        $searchQuery->where('veg', 1);
    }

    if (isset($additional_data['non_veg']) && $additional_data['non_veg'] == 1) {
        $searchQuery->where('non_veg', 1);
    }

    if (isset($additional_data['discount']) && $additional_data['discount'] == 1) {
        $searchQuery->where('discount', 1);
    }

    // Sorting
    if ($all_restaurant_default_status == '1') {
        $searchQuery->orderBy('open', 'desc')->orderBy('orders_count', 'desc');
    } else {
        if ($all_restaurant_sort_by_temp_closed == 'remove') {
            $searchQuery->where('active', '>', 0);
        } elseif ($all_restaurant_sort_by_temp_closed == 'last') {
            $searchQuery->orderByDesc('active');
        }

        if ($all_restaurant_sort_by_unavailable == 'remove') {
            $searchQuery->where('open', '>', 0);
        } elseif ($all_restaurant_sort_by_unavailable == 'last') {
            $searchQuery->orderBy('open', 'desc');
        }

        if ($all_restaurant_sort_by_general == 'latest_created') {
            $searchQuery->orderBy('created_at', 'desc');
        } elseif ($all_restaurant_sort_by_general == 'nearest_first') {
            $searchQuery->orderBy('distance', 'asc');
        } elseif ($all_restaurant_sort_by_general == 'rating') {
            $searchQuery->orderBy('avg_rating', 'desc');
        } elseif ($all_restaurant_sort_by_general == 'review_count') {
            $searchQuery->orderBy('reviews_count', 'desc');
        } elseif ($all_restaurant_sort_by_general == 'order_count') {
            $searchQuery->orderBy('orders_count', 'desc');
        } elseif ($all_restaurant_sort_by_general == 'a_to_z') {
            $searchQuery->orderBy('name', 'asc');
        } elseif ($all_restaurant_sort_by_general == 'z_to_a') {
            $searchQuery->orderBy('name', 'desc');
        }
    }

    // Ensure 'offset' is an integer
    $page = isset($additional_data['offset']) ? (int)$additional_data['offset'] : 1;

    // Paginate the results
    $paginator = $searchQuery->paginate($additional_data['limit'], 'page', $page);

    return [
        'filter_data' => $additional_data['filter'] ?? null,
        'total_size' => $paginator->total(),
        'limit' => $additional_data['limit'],
        'offset' => $additional_data['offset'],
        'restaurants' => $paginator->items(),
    ];
}


    public static function get_latest_restaurants(
        $zone_id,
        $limit = 10,
        $offset = 1,
        $type = 'all',
        $longitude = 0,
        $latitude = 0,
        $veg = null,
        $non_veg = null,
        $discount = null,
        $top_rated = null
    ) {
        $new_restaurant_default_status = \App\Models\BusinessSetting::where('key', 'new_restaurant_default_status')->first();
        $new_restaurant_default_status = $new_restaurant_default_status ? $new_restaurant_default_status->value : 1;
        $new_restaurant_sort_by_general = \App\Models\PriorityList::where('name', 'new_restaurant_sort_by_general')->where('type', 'general')->first();
        $new_restaurant_sort_by_general = $new_restaurant_sort_by_general ? $new_restaurant_sort_by_general->value : '';
        $new_restaurant_sort_by_unavailable = \App\Models\PriorityList::where('name', 'new_restaurant_sort_by_unavailable')->where('type', 'unavailable')->first();
        $new_restaurant_sort_by_unavailable = $new_restaurant_sort_by_unavailable ? $new_restaurant_sort_by_unavailable->value : '';
        $new_restaurant_sort_by_temp_closed = \App\Models\PriorityList::where('name', 'new_restaurant_sort_by_temp_closed')->where('type', 'temp_closed')->first();
        $new_restaurant_sort_by_temp_closed = $new_restaurant_sort_by_temp_closed ? $new_restaurant_sort_by_temp_closed->value : '';

        $query = Restaurant::withOpen($longitude, $latitude)
            ->with(['discount' => function ($q) {
                return $q->validate();
            }])->whereIn('zone_id', $zone_id)
            ->withcount('foods')
            ->withcount('reviews_comments')
            ->when(isset($veg)   && $veg == 1, function ($q) {
                $q->where('veg', 1);
            })
            ->when(isset($non_veg) && $non_veg == 1, function ($q) {
                $q->where('non_veg', 1);
            })
            ->when(isset($discount)  && $discount == 1, function ($q) {
                $q->whereHas('discount', function ($query) {
                    return $query->validate();
                });
            })
            ->when(isset($top_rated) && $top_rated == 1, function ($query) {
                $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id')
                        ->havingRaw('AVG(reviews.rating) > ?', [3]);
                }, 'avg_r')->having('avg_r', '>=', 3);
            })
            ->orderBy('distance', 'asc')
            ->Active()
            ->type($type);

        if ($new_restaurant_default_status == '1') {
            $query = $query->latest();
        } else {

            if ($new_restaurant_sort_by_temp_closed == 'remove') {
                $query = $query->where('active', '>', 0);
            } elseif ($new_restaurant_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('active');
            }

            if ($new_restaurant_sort_by_unavailable == 'remove') {
                $query = $query->having('open', '>', 0);
            } elseif ($new_restaurant_sort_by_unavailable == 'last') {
                $query = $query->orderBy('open', 'desc');
            }

            if ($new_restaurant_sort_by_general == 'latest_created') {
                $query = $query->latest();
            } elseif ($new_restaurant_sort_by_general == 'nearby_first') {
                $query = $query->orderBy('distance');
            } elseif ($new_restaurant_sort_by_general == 'delivery_time') {
                $query = $query->whereRaw("delivery_time REGEXP '^[0-9]+-[0-9]+-min$'")
                    ->orderByRaw("SUBSTRING_INDEX(delivery_time, '-', 1)")
                    ->orderByRaw("SUBSTRING_INDEX(SUBSTRING_INDEX(delivery_time, '-', -1), '-', 1)");
            }
        }

        $paginator = $query->limit(20)->get();

        return [
            'total_size' => $paginator->count(),
            'limit' => $limit,
            'offset' => $offset,
            'restaurants' => $paginator
        ];
    }

    public static function get_popular_restaurants($zone_id, $limit = 10, $offset = 1, $type = 'all', $longitude = 0, $latitude = 0, $veg = null, $non_veg = null, $discount = null, $top_rated = null)
    {
        $limit = $limit ?? 10;
        $offset = $offset ?? 1;
        $type = $type ?? 'all';
        $longitude = $longitude ?? 0;
        $latitude = $latitude ?? 0;
        $veg = $veg ?? null;
        $non_veg = $non_veg ?? null;
        $discount = $discount ?? null;
        $top_rated = $top_rated ?? null;

        // Add debug information

        $popular_restaurant_default_status = \App\Models\BusinessSetting::where('key', 'popular_restaurant_default_status')->first();
        $popular_restaurant_default_status = $popular_restaurant_default_status ? $popular_restaurant_default_status->value : 1;
        $popular_restaurant_sort_by_general = \App\Models\PriorityList::where('name', 'popular_restaurant_sort_by_general')->where('type', 'general')->first();
        $popular_restaurant_sort_by_general = $popular_restaurant_sort_by_general ? $popular_restaurant_sort_by_general->value : '';
        $popular_restaurant_sort_by_unavailable = \App\Models\PriorityList::where('name', 'popular_restaurant_sort_by_unavailable')->where('type', 'unavailable')->first();
        $popular_restaurant_sort_by_unavailable = $popular_restaurant_sort_by_unavailable ? $popular_restaurant_sort_by_unavailable->value : '';
        $popular_restaurant_sort_by_temp_closed = \App\Models\PriorityList::where('name', 'popular_restaurant_sort_by_temp_closed')->where('type', 'temp_closed')->first();
        $popular_restaurant_sort_by_temp_closed = $popular_restaurant_sort_by_temp_closed ? $popular_restaurant_sort_by_temp_closed->value : '';

        $query = Restaurant::withOpen($longitude, $latitude)
            ->with(['reviews', 'discount' => function ($q) {
                return $q->validate();
            }])->whereIn('zone_id', $zone_id)
            ->withcount('foods')
            ->withcount('reviews_comments')
            ->withCount('reviews')
            ->withCount('orders')
            ->type($type)
            ->when(isset($veg) && $veg == 1, function ($q) {
                $q->where('veg', 1);
            })
            ->when(isset($non_veg) && $non_veg == 1, function ($q) {
                $q->where('non_veg', 1);
            })
            ->when(isset($discount)  && $discount == 1, function ($q) {
                $q->whereHas('discount', function ($query) {
                    return $query->validate();
                });
            })
            ->when(isset($top_rated) && $top_rated == 1, function ($query) {
                $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id');
                }, 'avg_r')->having('avg_r', '>=', 3);
            })
            ->Active();

        if ($popular_restaurant_default_status == '1') {
            $query = $query->orderBy('open', 'desc')->orderBy('orders_count', 'desc');
        } else {

            if ($popular_restaurant_sort_by_temp_closed == 'remove') {
                $query = $query->where('active', '>', 0);
            } elseif ($popular_restaurant_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('active');
            }

            if ($popular_restaurant_sort_by_unavailable == 'remove') {
                $query = $query->having('open', '>', 0);
            } elseif ($popular_restaurant_sort_by_unavailable == 'last') {
                $query = $query->orderBy('open', 'desc');
            }

            if ($popular_restaurant_sort_by_general == 'rating') {
                $query = $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id');
                }, 'avg_r')->orderBy('avg_r', 'desc');
            } elseif ($popular_restaurant_sort_by_general == 'review_count') {
                $query = $query->orderByDesc('reviews_count');
            } elseif ($popular_restaurant_sort_by_general == 'order_count') {
                $query = $query->orderBy('orders_count', 'desc');
            }
        }

        $paginator = $query->limit(50)->get();

        return [
            'total_size' => $paginator->count(),
            'limit' => $limit,
            'offset' => $offset,
            'restaurants' => $paginator
        ];
    }

    public static function get_restaurant_details($restaurant_id)
    {
        return Restaurant::with(['discount' => function ($q) {
            return $q->validate();
        }, 'campaigns', 'schedules', 'restaurant_sub'])->active()
            ->withcount('reviews_comments')
            ->when(is_numeric($restaurant_id), function ($query) use ($restaurant_id) {
                $query->where('restaurant_id', $restaurant_id);
            })
            ->when(!is_numeric($restaurant_id), function ($query) use ($restaurant_id) {
                $query->where('slug', $restaurant_id);
            })
            ->first();
    }

    public static function calculate_restaurant_rating($ratings)
    {
        $total_submit = $ratings[0] + $ratings[1] + $ratings[2] + $ratings[3] + $ratings[4];
        $rating = ($ratings[0] * 5 + $ratings[1] * 4 + $ratings[2] * 3 + $ratings[3] * 2 + $ratings[4]) / ($total_submit ? $total_submit : 1);
        return ['rating' => $rating, 'total' => $total_submit];
    }
    public static function calculate_positive_rating($ratings)
    {
        $total_submit = $ratings[0] + $ratings[1] + $ratings[2] + $ratings[3] + $ratings[4];
        $rating = (($ratings[0] + $ratings[1]) / ($total_submit ? $total_submit : 1)) * 100;
        return ['rating' => $rating, 'total' => $total_submit];
    }

    public static function update_restaurant_rating($ratings, $product_rating)
    {
        $restaurant_ratings = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        if ($ratings) {
            $restaurant_ratings[1] = $ratings[4];
            $restaurant_ratings[2] = $ratings[3];
            $restaurant_ratings[3] = $ratings[2];
            $restaurant_ratings[4] = $ratings[1];
            $restaurant_ratings[5] = $ratings[0];
            $restaurant_ratings[$product_rating] = $ratings[5 - $product_rating] + 1;
        } else {
            $restaurant_ratings[$product_rating] = 1;
        }
        return json_encode($restaurant_ratings);
    }

    public static function search_restaurants($name, $zone_id, $category_id = null, $limit = 10, $offset = 1, $type = 'all', $longitude = 0, $latitude = 0, $popular = 0, $new = 0, $rating = 0, $rating_3_plus = 0, $rating_4_plus = 0, $rating_5 = 0, $discounted = 0, $sort_by = null)
    {
        $search_bar_default_status = \App\Models\BusinessSetting::where('key', 'search_bar_default_status')->first();
        $search_bar_default_status = $search_bar_default_status ? $search_bar_default_status->value : 1;
        $search_bar_sort_by_unavailable = \App\Models\PriorityList::where('name', 'search_bar_sort_by_unavailable')->where('type', 'unavailable')->first();
        $search_bar_sort_by_unavailable = $search_bar_sort_by_unavailable ? $search_bar_sort_by_unavailable->value : '';
        $search_bar_sort_by_temp_closed = \App\Models\PriorityList::where('name', 'search_bar_sort_by_temp_closed')->where('type', 'temp_closed')->first();
        $search_bar_sort_by_temp_closed = $search_bar_sort_by_temp_closed ? $search_bar_sort_by_temp_closed->value : '';

        $key = $name != 'null' ? explode(' ', $name) : null;
        // dd($rating_4_plus ,$rating_3_plus , );
        $query = Restaurant::withOpen($longitude, $latitude)->with(['discount' => function ($q) {
            return $q->validate();
        }])->whereIn('zone_id', $zone_id)->weekday()
            ->withcount('foods')
            ->withcount('reviews_comments')
            ->when(isset($key), function ($query) use ($key) {
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->Where('name', 'like', "%{$value}%");
                    }
                    $q->orWhereHas('tags', function ($query) use ($key) {
                        foreach ($key as $value) {
                            $query->where('tag', 'like', "%{$value}%");
                        };
                    });
                    $q->orWhereHas('cuisine', function ($query) use ($key) {
                        foreach ($key as $value) {
                            $query->where('name', 'like', "%{$value}%");
                        };
                    });
                    $q->orWhereHas('foods', function ($query) use ($key) {
                        foreach ($key as $value) {
                            $query->where('name', 'like', "%{$value}%");
                        };
                    });
                    $q->orWhereHas('translations', function ($query) use ($key) {
                        foreach ($key as $value) {
                            $query->where('translationable_type', 'App\Models\Restaurant')->where('key', 'name')->where('value', 'like', "%{$value}%");
                        };
                    });
                });
            })

            ->when($new == 1, function ($query) {
                return $query->latest();
            })
            ->when($popular == 1, function ($query) {
                return $query->withCount('orders')->orderBy('orders_count', 'desc');
            })

            ->when($rating == 1, function ($query) {
                $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id')
                        ->havingRaw('AVG(reviews.rating) > ?', [3]);
                }, 'avg_r')->having('avg_r', '>=', 3);
            })

            ->when($rating_5 == 1 && !($rating_4_plus  == 1 || $rating_3_plus == 1), function ($query) {
                $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id')
                        ->havingRaw('AVG(reviews.rating) > ?', [4]);
                }, 'rating_5')->having('rating_5', '>=', 5);
            })
            ->when(($rating_4_plus == 1 && !($rating_5  == 1 || $rating_3_plus == 1) || ($rating_4_plus == 1 && $rating_5  == 1 && $rating_3_plus != 1)), function ($query) {
                $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id')
                        ->havingRaw('AVG(reviews.rating) > ?', [4]);
                }, 'rating_4_plus')->having('rating_4_plus', '>', 4);
            })
            ->when($rating_3_plus == 1, function ($query) {
                $query->selectSub(function ($query) {
                    $query->selectRaw('AVG(reviews.rating)')
                        ->from('reviews')
                        ->join('food', 'food.id', '=', 'reviews.food_id')
                        ->whereColumn('food.restaurant_id', 'restaurants.restaurant_id')
                        ->groupBy('food.restaurant_id')
                        ->havingRaw('AVG(reviews.rating) > ?', [3]);
                }, 'rating_3_plus')->having('rating_3_plus', '>', 3);
            })
            ->when($discounted == 1, function ($q) {
                $q->whereHas('discount', function ($query) {
                    return $query->validate();
                });
            })

            ->when($category_id, function ($query) use ($category_id) {
                $query->whereHas('foods.category', function ($q) use ($category_id) {
                    return $q->whereId($category_id)->orWhere('parent_id', $category_id);
                });
            })
            ->when(isset($sort_by) && $sort_by == 'asc', function ($query) use ($sort_by) {
                return $query->orderBy('name', 'asc');
            })
            ->when(isset($sort_by) && $sort_by == 'desc', function ($query) use ($sort_by) {
                return $query->orderBy('name', 'desc');
            })
            ->when(isset($sort_by) && $sort_by == 'high' ||  $sort_by == 'low', function ($query) {
                return $query->latest();
            })

            ->active()->type($type);
        if ($search_bar_default_status == '1') {
            $query = $query->orderByRaw("FIELD(name, ?) DESC", [$name])
                ->orderBy('open', 'desc')
                ->orderBy('distance');
        }

        if ($search_bar_default_status == '0') {
            if ($search_bar_sort_by_temp_closed == 'remove') {
                $query = $query->where('active', '>', 0);
            } elseif ($search_bar_sort_by_temp_closed == 'last') {
                $query = $query->orderByDesc('active');
            }

            if ($search_bar_sort_by_unavailable == 'remove') {
                $query = $query->having('open', '>', 0);
            } elseif ($search_bar_sort_by_unavailable == 'last') {
                $query = $query->orderBy('open', 'desc');
            }
        }

        $paginator = $query->paginate($limit, ['*'], 'page', $offset);


        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'restaurants' => $paginator->items()
        ];
    }

    public static function get_overall_rating($reviews)
    {
        $totalRating = count($reviews);
        $rating = 0;
        foreach ($reviews as $key => $review) {
            $rating += $review->rating;
        }
        if ($totalRating == 0) {
            $overallRating = 0;
        } else {
            $overallRating = number_format($rating / $totalRating, 2);
        }

        return [$overallRating, $totalRating];
    }

    public static function get_earning_data($vendor_id)
    {
        $monthly_earning = OrderTransaction::whereMonth('created_at', date('m'))->NotRefunded()->where('vendor_id', $vendor_id)->sum('restaurant_amount');
        $weekly_earning = OrderTransaction::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->NotRefunded()->where('vendor_id', $vendor_id)->sum('restaurant_amount');
        $daily_earning = OrderTransaction::whereDate('created_at', now())->NotRefunded()->where('vendor_id', $vendor_id)->sum('restaurant_amount');

        return ['monthely_earning' => (float)$monthly_earning, 'weekly_earning' => (float)$weekly_earning, 'daily_earning' => (float)$daily_earning];
    }

    public static function format_export_restaurants($restaurants)
    {
        $storage = [];
        foreach ($restaurants as $item) {
            $storage[] = [
                'id' => $item->restaurants[0]->id ?? null,
                'ownerID' => $item->id ?? null,
                'ownerFirstName' => $item->f_name ?? null,
                'ownerLastName' => $item->l_name ?? null,
                'restaurantName' => $item->restaurants[0]->name ?? null,
                'CoverPhoto' => $item->restaurants[0]->cover_photo ?? null,
                'logo' => $item->restaurants[0]->logo ?? null,
                'phone' => $item->phone ?? null,
                'email' => $item->email ?? null,
                'latitude' => $item->restaurants[0]->latitude ?? null,
                'longitude' => $item->restaurants[0]->longitude ?? null,
                'zone_id' => $item->restaurants[0]->zone_id ?? null,
                'Address' => $item->restaurants[0]->address ?? null,
                'Slug' => $item->restaurants[0]->slug ?? null,
                'MinimumOrderAmount' => $item->restaurants[0]->minimum_order ?? null,
                'Comission' => $item->restaurants[0]->comission ?? 0,
                'Tax' => $item->restaurants[0]->tax ?? 0,
                'DeliveryTime' => $item->restaurants[0]->delivery_time ?? '20-30',
                'MinimumDeliveryFee' => $item->restaurants[0]->minimum_shipping_charge ?? 0,
                'PerKmDeliveryFee' => $item->restaurants[0]->per_km_shipping_charge ?? 0,
                'MaximumDeliveryFee' => $item->restaurants[0]->maximum_shipping_charge ?? 0,
                'RestaurantModel' => $item->restaurants[0]->restaurant_model ?? null,
                'ScheduleOrder' => isset($item->restaurants[0]->schedule_order) ? ($item->restaurants[0]->schedule_order == 1 ? 'yes' : 'no') : null,
                'FreeDelivery' => isset($item->restaurants[0]->free_delivery) ? ($item->restaurants[0]->free_delivery == 1 ? 'yes' : 'no') : null,
                'TakeAway' => isset($item->restaurants[0]->take_away) ? ($item->restaurants[0]->take_away == 1 ? 'yes' : 'no') : null,
                'Delivery' => isset($item->restaurants[0]->delivery) ? ($item->restaurants[0]->delivery == 1 ? 'yes' : 'no') : null,
                'Veg' => isset($item->restaurants[0]->veg) ? ($item->restaurants[0]->veg == 1 ? 'yes' : 'no') : null,
                'NonVeg' => isset($item->restaurants[0]->non_veg) ? ($item->restaurants[0]->non_veg == 1 ? 'yes' : 'no') : null,
                'OrderSubscription' => isset($item->restaurants[0]->order_subscription_active) ? ($item->restaurants[0]->order_subscription_active == 1 ? 'yes' : 'no') : null,
                'Status' => isset($item->restaurants[0]->status) ? ($item->restaurants[0]->status == 1 ? 'active' : 'inactive') : null,
                'FoodSection' => isset($item->restaurants[0]->food_section) ? ($item->restaurants[0]->food_section == 1 ? 'active' : 'inactive') : null,
                'ReviewsSection' => isset($item->restaurants[0]->reviews_section) ? ($item->restaurants[0]->reviews_section == 1 ? 'active' : 'inactive') : null,
                'SelfDeliverySystem' => isset($item->restaurants[0]->self_delivery_system) ? ($item->restaurants[0]->self_delivery_system == 1 ? 'active' : 'inactive') : null,
                'PosSystem' => isset($item->restaurants[0]->pos_system) ? ($item->restaurants[0]->pos_system == 1 ? 'active' : 'inactive') : null,
                'RestaurantOpen' => isset($item->restaurants[0]->active) ? ($item->restaurants[0]->active == 1 ? 'yes' : 'no') : null,
                // 'gst' => $item->restaurants[0]->gst ?? null,
            ];
        }

        return $storage;
    }
    public static function format_restaurant_report_export_data($restaurants)
    {
        $storage = [];
        foreach ($restaurants as $key => $restaurant) {
            if ($restaurant->count() < 1) {
                break;
            }
            if ($restaurant->reviews_count) {
                $reviews_count = $restaurant->reviews_count;
            } else {
                $reviews_count = 1;
            }

            $restaurant_rating = round($restaurant->reviews_sum_rating / $reviews_count, 1);
            $storage[] = [
                '#' => $key + 1,
                translate('messages.restaurant') => $restaurant->name,
                translate('messages.total_food') => $restaurant->foods_count ?? 0,
                translate('messages.total_order') => $restaurant->without_refund_total_orders_count ?? 0,
                translate('messages.total_order') . translate('messages.amount') => $restaurant->transaction_sum_order_amount ?? 0,
                translate('messages.total_discount_given') => $restaurant->transaction_sum_restaurant_expense ?? 0,
                translate('messages.total_admin_commission') => $restaurant->transaction_sum_admin_commission ?? 0,
                translate('messages.total_vat_tax') => $restaurant->transaction_sum_tax ?? 0,
                translate('messages.average_ratings') => $restaurant_rating,
            ];
        }
        return $storage;
    }

    public static function recently_viewed_restaurants_data($zone_id, $limit = 10, $offset = 1, $type = 'all', $longitude = 0, $latitude = 0)
    {
        $user_id = null;
        if (auth('api')->user() !== null) {
            $user_id = auth('api')->user()->id;
        }

        $paginator = Restaurant::whereHas('users', function ($query) use ($user_id) {
            $query->where('user_id', $user_id);
        })
            ->withOpen($longitude, $latitude)
            ->with(['discount' => function ($q) {
                return $q->validate();
            }])->whereIn('zone_id', $zone_id)
            ->withcount('foods')
            ->withcount('reviews_comments')
            ->Active()
            ->type($type)
            ->withCount('orders')
            ->orderBy('open', 'desc')
            ->selectRaw(
                '(SELECT `visit_count` FROM `visitor_logs` WHERE `restaurants`.`restaurant_id` = `visitor_logs`.`visitor_log_id`
            AND `user_id` = ? ORDER BY `visit_count` DESC LIMIT 1) as v_count,
            (SELECT `order_count` FROM `visitor_logs` WHERE `restaurants`.`restaurant_id` = `visitor_logs`.`visitor_log_id`
            AND `user_id` = ? ORDER BY  `order_count` DESC LIMIT 1) as o_count',
                [$user_id, $user_id]
            )
            ->orderBy('o_count', 'desc')
            ->orderBy('v_count', 'desc')
            ->limit(50)
            ->get();
        return [
            'total_size' => $paginator->count(),
            'limit' => $limit,
            'offset' => $offset,
            'restaurants' => $paginator
        ];
    }
}
