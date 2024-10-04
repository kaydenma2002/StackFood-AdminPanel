<?php
namespace App\Jobs;

use App\Models\Food;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CacheFoodOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $food;

    public function __construct(Food $food)
    {
        $this->food = $food;
    }

    public function handle()
    {
        try {
            $cacheKey = "food_orders_{$this->food->id}";

            if (!Cache::has($cacheKey)) {
                $orders = $this->food->orders()->get()->toArray();
                Cache::put($cacheKey, $orders, 60 * 60); // Cache for 60 minutes
            } else {
                $orders = Cache::get($cacheKey);
            }

            $orders = collect($orders);

            if (
                $orders->isNotEmpty() &&
                $orders->where('created_at', '>=', now()->startOfDay())->isEmpty() &&
                $this->food->stock_type == 'daily'
            ) {
                $this->food->sell_count = 0;
                $this->food->save();
                $this->food->newVariationOptions()->update(['sell_count' => 0]);
            }
        } catch (\Exception $exception) {
            info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
        }
    }
}
