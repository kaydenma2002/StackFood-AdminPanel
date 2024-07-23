<?php
namespace App\Http\Controllers\Api\V1;

use App\Models\Advertisement;
use App\Http\Controllers\Controller;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdvertisementController extends Controller
{
    public function get_adds()
    {
        $Advertisement= Advertisement::valid()->with('restaurant')->orderByRaw('ISNULL(priority), priority ASC')
        ->get();

        try {
            $Advertisement->each(function ($advertisement) {
                $advertisement->reviews_comments_count = (int) $advertisement->restaurant->reviews_comments()->count(); 
                $reviewsInfo = $advertisement->restaurant->reviews()
                ->selectRaw('avg(reviews.rating) as average_rating, count(reviews.id) as total_reviews, food.restaurant_id')
                ->groupBy('food.restaurant_id')
                ->first();

                $advertisement->average_rating = (float)  $reviewsInfo?->average_rating ?? 0; 
                // unset($advertisement->restaurant);
            });
        } catch (\Exception $e) {
            info($e->getMessage());
        }

        return response()->json($Advertisement, 200);
    }

}
