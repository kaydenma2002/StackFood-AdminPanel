<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\RestaurantLogic;
use App\Http\Controllers\Controller;
use App\Models\Food;
use App\Models\Restaurant;
use App\Models\Review;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function get_latest_products(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required',
            'category_id' => 'required',
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $type = $request->query('type', 'all');

        $products = ProductLogic::get_latest_products(limit: $request['limit'], offset: $request['offset'], restaurant_id: $request['restaurant_id'], category_id: $request['category_id'], type: $type);
        $products['products'] = Helpers::product_data_formatting(data: $products['products'], multi_data: true, trans: false, local: app()->getLocale());
        return response()->json($products, 200);
    }

    public function get_searched_products(Request $request)
    {

        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $zone_id = json_decode($request->header('zoneId'), true);
        $search_query = isset($request['name']) && $request['name'] != 'null' ? $request['name'] : '';
        $limit = (int)($request['limit'] ?? 10);
        $offset = (int)($request['offset'] ?? 1);
        $page = $offset;
        $type = $request->query('type', 'all');

        // Initialize MeiliSearch query
        $meili_query = [
            'filter' => ["zone_id IN [" . implode(',', $zone_id) . "]", "active = true"],
            'limit' => $limit,
            'offset' => ($page - 1) * $limit,
        ];

        // Add type filter
        if ($type == 'veg') {
            $meili_query['filter'][] = "veg = true";
        } elseif ($type == 'non_veg') {
            $meili_query['filter'][] = "veg = false";
        }

        // Add other filters
        if ($request->category_id) {
            $meili_query['filter'][] = "category_id = {$request->category_id} OR parent_category_id = {$request->category_id}";
        }
        if ($request->restaurant_id) {
            $meili_query['filter'][] = "restaurant_id = {$request->restaurant_id}";
        }
        if ($request->rating_3_plus == 1) {
            $meili_query['filter'][] = "avg_rating > 3";
        }
        if ($request->rating_4_plus == 1) {
            $meili_query['filter'][] = "avg_rating > 4";
        }
        if ($request->rating_5 == 1) {
            $meili_query['filter'][] = "avg_rating >= 5";
        }
        if ($request->discounted == 1) {
            $meili_query['filter'][] = "discount > 0";
        }

        // Add sorting
        if (isset($request->sort_by)) {
            switch ($request->sort_by) {
                case 'asc':
                    $meili_query['sort'] = ['name:asc'];
                    break;
                case 'desc':
                    $meili_query['sort'] = ['name:desc'];
                    break;
                case 'low':
                    $meili_query['sort'] = ['price:asc'];
                    break;
                case 'high':
                    $meili_query['sort'] = ['price:desc'];
                    break;
            }
        }

        // Perform the MeiliSearch query

        $search_results = Food::search($search_query, function ($meiliSearch, $query, $options) use ($meili_query) {

            $options = array_merge($options, $meili_query);

            return $meiliSearch->search($query, $options);
        });


        $products = $search_results->take($limit)->get();



        // Ensure that 'estimatedTotalHits' exists in the raw response
        $total = $search_results->raw()['estimatedTotalHits'] ?? $products->count(); // Fallback to count if 'estimatedTotalHits' is missing

        // Additional processing for restaurant-related searches
        if ($search_query) {

            $restaurant_results = Restaurant::search($search_query)
                ->get();

            foreach ($restaurant_results as $restaurant) {

                $restaurant_foods = $restaurant->foods()
                    ->active()
                    ->type($type)
                    ->take($limit - $products->count())
                    ->get();

                $products = $products->concat($restaurant_foods);

                if ($products->count() >= $limit) {
                    break;
                }
            }

            $total += $restaurant_results->count();
        }

        $data = [
            'total_size' => $total,
            'limit' => $limit,
            'offset' => $offset,
            'products' => $products->take($limit)->values()
        ];


// Use dd() to dump the products value

        $data['products'] = Helpers::product_data_formatting(data: $data['products'], multi_data: true, trans: false,
        local: app()->getLocale());
        return response()->json($data, 200);
    }


    public function get_popular_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $zone_id = json_decode($request->header('zoneId'), true);
        $products = ProductLogic::popular_products(zone_id: $zone_id, limit: $request['limit'], offset: $request['offset'], type: $type, longitude: $longitude, latitude: $latitude);
        $products['products'] = Helpers::product_data_formatting(data: $products['products'], multi_data: true, trans: false, local: app()->getLocale());
        return response()->json($products, 200);
    }

    public function get_most_reviewed_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $type = $request->query('type', 'all');
        $zone_id = json_decode($request->header('zoneId'), true);
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');
        $products = ProductLogic::most_reviewed_products(zone_id: $zone_id, limit: $request['limit'], offset: $request['offset'], type: $type, longitude: $longitude, latitude: $latitude);
        $products['products'] = Helpers::product_data_formatting(data: $products['products'], multi_data: true, trans: false, local: app()->getLocale());
        return response()->json($products, 200);
    }

    public function get_product($id)
    {


        $product = ProductLogic::get_product($id, request()?->campaign ? true : false);
        $product = Helpers::product_data_formatting(data: $product, multi_data: false, trans: false, local: app()->getLocale());
        return response()->json($product, 200);
    }

    public function get_related_products($id)
    {
        if (Food::find($id)) {
            $products = ProductLogic::get_related_products(product_id: $id);
            $products = Helpers::product_data_formatting(data: $products, multi_data: true, trans: false, local: app()->getLocale());
            return response()->json($products, 200);
        }
        return response()->json([
            'errors' => ['code' => 'product-001', 'message' => translate('messages.not_found')]
        ], 404);
    }

    public function get_set_menus()
    {
        try {
            $products = Helpers::product_data_formatting(data: Food::active()->with(['rating'])->where(['set_menu' => 1, 'status' => 1])->get(), multi_data: true, trans: false, local: app()->getLocale());
            return response()->json($products, 200);
        } catch (\Exception $e) {
            info($e->getMessage());
            return response()->json([
                'errors' => ['code' => 'product-001', 'message' => 'Set menu not found!']
            ], 404);
        }
    }

    public function get_product_reviews($food_id)
    {
        $reviews = Review::with(['customer', 'food', 'restaurant'])->where(['food_id' => $food_id])->active()->get();

        $storage = [];
        foreach ($reviews as $item) {
            $item['attachment'] = json_decode($item['attachment']);
            $item['food_name'] = null;
            if ($item?->food) {
                $item['food_name'] = $item?->food?->name;
                if (count($item?->food?->translations) > 0) {
                    $translate = array_column($item->food->translations->toArray(), 'value', 'key');
                    $item['food_name'] = $translate['name'];
                }
            }
            unset($item['food']);
            array_push($storage, $item);
        }
        return response()->json($storage, 200);
    }

    public function get_product_rating($id)
    {
        try {
            $product = Food::find($id);
            $overallRating = ProductLogic::get_overall_rating(reviews: $product->reviews);
            return response()->json(floatval($overallRating[0]), 200);
        } catch (\Exception $e) {
            return response()->json(['errors' => $e->getMessage()], 403);
        }
    }

    public function submit_product_review(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'food_id' => 'required',
            'order_id' => 'required',
            'comment' => 'nullable',
            'rating' => 'required|numeric|max:5',
            'attachment.*' => 'nullable|max:2048',
        ]);

        $product = Food::find($request->food_id);
        if (isset($product) == false) {
            $validator->errors()->add('food_id', translate('messages.food_not_found'));
        }

        $multi_review = Review::where(['food_id' => $request->food_id, 'user_id' => $request?->user()?->id, 'order_id' => $request->order_id])->first();
        if (isset($multi_review)) {
            return response()->json([
                'errors' => [
                    ['code' => 'review', 'message' => translate('messages.already_submitted')]
                ]
            ], 403);
        } else {
            $review = new Review;
        }

        if ($validator->errors()->count() > 0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image_array = [];
        if (!empty($request->file('attachment'))) {
            foreach ($request->file('attachment') as $image) {
                if ($image != null) {
                    if (!Storage::disk('public')->exists('review')) {
                        Storage::disk('public')->makeDirectory('review');
                    }
                    array_push($image_array, Storage::disk('public')->put('review', $image));
                }
            }
        }

        $review->user_id = $request?->user()?->id;
        $review->food_id = $request->food_id;
        $review->order_id = $request->order_id;
        $review->comment = $request->comment;
        $review->rating = $request->rating;
        $review->attachment = json_encode($image_array);
        $review->save();

        if ($product->restaurant) {
            $restaurant_rating = RestaurantLogic::update_restaurant_rating(ratings: $product?->restaurant?->rating, product_rating: $request->rating);
            $product->restaurant->rating = $restaurant_rating;
            $product?->restaurant?->save();
        }

        $product->rating = ProductLogic::update_rating(ratings: $product->rating, product_rating: $request->rating);
        $product->avg_rating = ProductLogic::get_avg_rating(rating: json_decode($product->rating, true));
        $product?->save();
        $product->increment('rating_count');

        return response()->json(['message' => translate('messages.review_submited_successfully')], 200);
    }


    public function get_recommended(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $type = $request->query('type', 'all');
        $key = explode(' ', $request['name']);
        $zone_id = json_decode($request->header('zoneId'), true);
        $products = ProductLogic::recommended_products(zone_id: $zone_id, restaurant_id: $request->restaurant_id, limit: $request['limit'], offset: $request['offset'], type: $type, name: $key);
        $products['products'] = Helpers::product_data_formatting(data: $products['products'], multi_data: true, trans: false, local: app()->getLocale());
        return response()->json($products, 200);
    }




    public function food_or_restaurant_search(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        if (!$request->hasHeader('longitude') || !$request->hasHeader('latitude')) {
            $errors = [];
            array_push($errors, ['code' => 'longitude-latitude', 'message' => translate('messages.longitude-latitude_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zone_id = json_decode($request->header('zoneId'), true);
        $longitude = $request->header('longitude');
        $latitude = $request->header('latitude');

        $key = $request->name;

        // Search foods using Meilisearch
        $foods = Food::search($key)
            ->where('active', true)
            ->whereIn('zone_id', $zone_id)
            ->take(50)
            ->get(['restaurant_id', 'name', 'image']);

        $restaurants = Restaurant::search($key)
            ->where('active', true)
            ->whereIn('zone_id', $zone_id)
            ->take(50)
            ->get();
        $restaurantIds = $restaurants->pluck('restaurant_id')->toArray();

        $restaurants = Restaurant::whereIn('restaurant_id', $restaurantIds)
            ->withOpen($longitude, $latitude)
            ->get();

        return [
            'foods' => $foods,
            'restaurants' => $restaurants
        ];
    }



    public function get_restaurant_popular_products(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $type = $request->query('type', 'all');
        $key = explode(' ', $request['name']);

        $zone_id = json_decode($request->header('zoneId'), true);
        $products = ProductLogic::get_restaurant_popular_products(zone_id: $zone_id, restaurant_id: $request->restaurant_id, type: $type, name: $key);
        $products['products'] = Helpers::product_data_formatting(data: $products['products'], multi_data: true, trans: false, local: app()->getLocale());
        return response()->json($products, 200);
    }


    public function recommended_most_reviewed(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $validator = Validator::make($request->all(), [
            'restaurant_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $type = $request->query('type', 'all');
        $key = explode(' ', $request['name']);

        $zone_id = json_decode($request->header('zoneId'), true);
        $products = ProductLogic::recommended_most_reviewed(zone_id: $zone_id, restaurant_id: $request->restaurant_id, type: $type, name: $key);


        $products = Helpers::product_data_formatting(data: $products, multi_data: true, trans: false, local: app()->getLocale());
        return response()->json($products, 200);
    }
}
