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
            // Retrieve default values or use fallback
            $category_list_default_status = Cache::remember('category_list_default_status', 60, function () {
                return BusinessSetting::where('key', 'category_list_default_status')->first()->value ?? 1;
            });

            $category_list_sort_by_general = Cache::remember('category_list_sort_by_general', 60, function () {
                return PriorityList::where('name', 'category_list_sort_by_general')->where('type', 'general')->first()->value ?? '';
            });

            $zone_id = $request->header('zoneId') ? json_decode($request->header('zoneId'), true) : [];
            $name = $request->query('name');

            // Build the query for categories
            $categoriesQuery = Category::withCount(['products', 'childes'])
                ->with(['childes' => function ($query) {
                    $query->withCount(['products', 'childes']);
                }])
                ->where(['position' => 0, 'status' => 1]);

            // Apply search filter if a name is provided
            if ($name) {
                $key = explode(' ', $name);
                $categoriesQuery->where(function ($query) use ($key) {
                    foreach ($key as $value) {
                        $query->orWhere('name', 'like', '%' . $value . '%')
                            ->orWhere('slug', 'like', '%' . $value . '%');
                    }
                });
            }

            // Apply sorting based on the default status and priority
            if ($category_list_default_status == 1) {
                $categoriesQuery->orderBy('priority', 'desc');
            } else {
                switch ($category_list_sort_by_general) {
                    case 'latest':
                        $categoriesQuery->latest();
                        break;
                    case 'oldest':
                        $categoriesQuery->oldest();
                        break;
                    case 'a_to_z':
                        $categoriesQuery->orderBy('name');
                        break;
                    case 'z_to_a':
                        $categoriesQuery->orderBy('name', 'desc');
                        break;
                    case 'order_count':
                        // We handle this sorting later after fetching data
                        break;
                }
            }

            // Get the count of categories matching the conditions
            $totalCategories = $categoriesQuery->count();

            // Calculate a random offset
            $randomOffset = max(0, $totalCategories - 20);
            $randomOffset = rand(0, $randomOffset);

            // Retrieve the categories with limit and random offset
            $categories = $categoriesQuery->skip($randomOffset)->take(20)->get();

            // Optional: Shuffle categories in PHP to ensure randomness
            $categories = $categories->shuffle();

            if (count($zone_id) > 0) {
                $categories->load(['products' => function ($query) use ($zone_id) {
                    $query->whereHas('restaurant', function ($query) use ($zone_id) {
                        $query->whereIn('zone_id', $zone_id);
                    });
                }]);

                foreach ($categories as $category) {
                    $productCount = $category->products->count();
                    $orderCount = $category->products->sum('order_count');

                    $category->products_count = $productCount;
                    $category->order_count = $orderCount;
                }

                if ($category_list_default_status != 1 && $category_list_sort_by_general == 'order_count') {
                    $categories = $categories->sortByDesc('order_count')->values()->all();
                }
            }

            return response()->json(Helpers::category_data_formatting($categories, true), 200);
        } catch (\Exception $e) {
            // Consider logging the error for debugging
            \Log::error('Error fetching categories: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching categories.'], 500);
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
