<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Support\Facades\Cache;

use App\Models\Food;
use App\Models\Category;
use App\Models\PriorityList;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\CentralLogics\CategoryLogic;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function get_categories(Request $request)
    {
        try {
            // Fetching settings
            $settings = BusinessSetting::whereIn('key', ['category_list_default_status', 'category_list_sort_by_general'])
                ->pluck('value', 'key')
                ->toArray();

            $category_list_default_status = $settings['category_list_default_status'] ?? 1;
            $category_list_sort_by_general = $settings['category_list_sort_by_general'] ?? '';

            $zone_id = json_decode($request->header('zoneId'), true) ?? [];
            $name = $request->query('name');

            // Generate a cache key based on the query parameters
            $cacheKey = 'categories_' . md5(json_encode([
                'zone_id' => $zone_id,
                'name' => $name,
                'category_list_default_status' => $category_list_default_status,
                'category_list_sort_by_general' => $category_list_sort_by_general,
            ]));

            // Cache for 10 minutes (or any duration you prefer)
            $categories = Cache::remember($cacheKey, now()->addMinutes(10), function() use ($category_list_default_status, $category_list_sort_by_general, $zone_id, $name) {
                $query = Category::withCount(['products', 'childes'])
                    ->with(['childes' => function($query) {
                        $query->withCount(['products', 'childes']);
                    }])
                    ->where(['position' => 0, 'status' => 1]);

                // Filter by name if provided
                if ($name) {
                    $keywords = explode(' ', $name);
                    $query->where(function($q) use ($keywords) {
                        foreach ($keywords as $value) {
                            $q->orWhere('name', 'like', "%{$value}%")
                              ->orWhere('slug', 'like', "%{$value}%");
                        }
                    });
                }

                // Apply sorting based on settings
                if ($category_list_default_status == 1) {
                    $query->orderBy('priority', 'desc');
                } else {
                    switch ($category_list_sort_by_general) {
                        case 'latest':
                            $query->latest();
                            break;
                        case 'oldest':
                            $query->oldest();
                            break;
                        case 'a_to_z':
                            $query->orderBy('name');
                            break;
                        case 'z_to_a':
                            $query->orderBy('name', 'desc');
                            break;
                    }
                }

                return $query->get();
            });

            // If zone_id is provided, calculate product count and order count
            if (count($zone_id) > 0) {
                $categories = $categories->map(function ($category) use ($zone_id) {
                    $productAndOrderCount = Food::active()
                        ->whereHas('restaurant', function ($query) use ($zone_id) {
                            $query->whereIn('zone_id', $zone_id);
                        })
                        ->whereHas('category', function($q) use ($category) {
                            return $q->whereId($category->id)->orWhere('parent_id', $category->id);
                        })
                        ->selectRaw('COUNT(*) as product_count, SUM(order_count) as total_order_count')
                        ->first();

                    $category->products_count = $productAndOrderCount->product_count ?? 0;
                    $category->order_count = $productAndOrderCount->total_order_count ?? 0;

                    return $category;
                });

                if ($category_list_default_status != 1 && $category_list_sort_by_general == 'order_count') {
                    $categories = $categories->sortByDesc('order_count')->values();
                }

                return response()->json($categories, 200);
            }

            return response()->json(Helpers::category_data_formatting($categories, false), 200);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()]);
        }
    }



    public function get_childes($id)
    {
        try {
            $categories = Category::when(is_numeric($id), function ($qurey) use ($id) {
                $qurey->where(['parent_id' => $id, 'status' => 1]);
            })
                ->when(!is_numeric($id), function ($qurey) use ($id) {
                    $qurey->where(['slug' => $id, 'status' => 1]);
                })
                ->orderBy('priority', 'desc')->get();
            return response()->json(Helpers::category_data_formatting($categories, true), 200);
        } catch (\Exception $e) {
            return response()->json([$e->getMessage()], 200);
        }
    }

    public function get_products($id, Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $additional_data = [
            'category_id' =>  $id,
            'zone_id' => json_decode($request->header('zoneId'), true),
            'limit' =>  $request['limit'] ?? 25,
            'offset' =>  $request['offset'] ?? 1,
            'type' =>  $request->query('type', 'all') ?? 'all',
            'veg' =>  $request->veg ?? 0,
            'non_veg' =>  $request->non_veg ?? 0,
            'new' =>  $request->new ?? 0,
            'avg_rating' => $request->avg_rating ?? 0,
            'top_rated' =>  $request->top_rated ?? 0,
            'start_price' => json_decode($request->price)[0] ?? 0,
            'end_price' => json_decode($request->price)[1] ?? 0,
            'longitude' => $request->header('longitude') ?? 0,
            'latitude' => $request->header('latitude') ?? 0,
        ];


        $data = CategoryLogic::products($additional_data);

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());

        if (auth('api')->user() !== null) {

            $customer_id = auth('api')->user()->id;
            Helpers::visitor_log('category', $customer_id, $id, false);
        }

        return response()->json($data, 200);
    }


    public function get_restaurants($id, Request $request)
    {

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $additional_data = [
            'category_id' => $id,
            'zone_id' => json_decode($request->header('zoneId'), true),
            'limit' => $request['limit'] ?? 25,
            'offset' => $request['offset'] ?? 1,
            'type' => $request->query('type', 'all') ?? 'all',
            'longitude' => $request->header('longitude') ?? 0,
            'latitude' => $request->header('latitude') ?? 0,
            'veg' => $request->veg ?? 0,
            'non_veg' => $request->non_veg ?? 0,
            'new' => $request->new ?? 0,
            'avg_rating' => $request->avg_rating ?? 0,
            'top_rated' => $request->top_rated ?? 0,
        ];

        $cacheKey = 'category_restaurants_' . implode('_', array_map('json_encode', array_values($additional_data)));

        $data = Cache::remember($cacheKey, 60, function () use ($additional_data) {
            $result = CategoryLogic::restaurants($additional_data);
            $result['restaurants'] = Helpers::restaurant_data_formatting($result['restaurants'], true);
            return $result;
        });

        return response()->json($data, 200);
    }




    public function get_all_products($id, Request $request)
    {
        try {
            return response()->json(Helpers::product_data_formatting(CategoryLogic::all_products($id), true, false, app()->getLocale()), 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }
}
