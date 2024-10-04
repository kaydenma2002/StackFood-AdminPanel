<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Scopes\ZoneScope;
use Illuminate\Support\Str;
use App\Scopes\RestaurantScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\ReportFilter;
use Laravel\Scout\Searchable;
use App\Jobs\CacheFoodOrders;

class Food extends Model
{
    use HasFactory, ReportFilter, Searchable;

    // Removed the redundant $with property to avoid unnecessary eager loading
    //public $with = ['orders', 'storage','translations'];
    protected $with = ['category'];
    protected $casts = [
        'tax' => 'float',
        'price' => 'float',
        'status' => 'integer',
        'discount' => 'float',
        'avg_rating' => 'float',
        'set_menu' => 'integer',
        'category_id' => 'integer',
        'restaurant_id' => 'integer',
        'reviews_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'veg' => 'integer',
        'min' => 'integer',
        'max' => 'integer',
        'maximum_cart_quantity' => 'integer',
        'recommended' => 'integer',
        'order_count' => 'integer',
        'rating_count' => 'integer',
        'is_halal' => 'integer',
    ];

    protected $appends = ['image_full_url'];

    // Accessor for full image URL
    public function getImageFullUrlAttribute()
    {
        $value = $this->image;
        if ($this->storage->isNotEmpty()) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'image') {
                    return Helpers::get_full_url('product', $value, $storage['value']);
                }
            }
        }
        return Helpers::get_full_url('product', $value, 'public');
    }

    // Relationships

    public function logs()
    {
        return $this->hasMany(Log::class, 'model_id')->where('model', 'Food');
    }

    public function newVariations()
    {
        return $this->hasMany(Variation::class, 'food_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class, 'food_id');
    }

    public function newVariationOptions()
    {
        return $this->hasMany(VariationOption::class, 'food_id');
    }

    public function carts()
    {
        return $this->morphMany(Cart::class, 'item');
    }

    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function rating()
    {
        return $this->hasMany(Review::class)
            ->select(DB::raw('avg(rating) average, count(food_id) rating_count, food_id'))
            ->groupBy('food_id');
    }

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class, 'restaurant_id', 'restaurant_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function orders()
    {
        return $this->hasMany(OrderDetail::class, 'food_id');
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }

    // Scopes

    public function scopeRecommended($query)
    {
        return $query->where('recommended', 1);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 1)->whereHas('restaurant', function ($query) {
            return $query->where('status', 1);
        });
    }

    public function scopeAvailable($query, $time)
    {
        return $query->where(function ($q) use ($time) {
            $q->where('available_time_starts', '<=', $time)
                ->where('available_time_ends', '>=', $time);
        });
    }

    public function scopePopular($query)
    {
        return $query->orderBy('order_count', 'desc');
    }

    public function scopeType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('veg', true);
        } else if ($type == 'non_veg') {
            return $query->where('veg', false);
        }
        return $query;
    }

    // Boot method to handle model events

    protected static function booted()
    {
        // Apply global scopes based on user authentication
        if (auth('vendor')->check() || auth('vendor_employee')->check()) {
            static::addGlobalScope(new RestaurantScope);
        }

        static::addGlobalScope(new ZoneScope);

        // Load storage and translations with conditions
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });

        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($food) {
            $food->slug = $food->generateSlug($food->name);
            $food->save();
        });

        static::retrieved(function ($food) {
            CacheFoodOrders::dispatch($food);
        });

        static::saved(function ($model) {
            if ($model->isDirty('image')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Clear the cache after saving
                cache()->forget("food_orders_{$model->id}");
            }
        });
    }



    private function generateSlug($name)
    {
        $slug = Str::slug($name);
        if ($max_slug = static::where('slug', 'like', "{$slug}%")->latest('id')->value('slug')) {
            if ($max_slug == $slug) return "{$slug}-2";

            $max_slug = explode('-', $max_slug);
            $count = array_pop($max_slug);
            if (isset($count) && is_numeric($count)) {
                $max_slug[] = ++$count;
                return implode('-', $max_slug);
            }
        }
        return $slug;
    }

    // Accessor for name with translation fallback
    public function getNameAttribute($value)
    {
        if ($this->translations->isNotEmpty()) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'name') {
                    return $translation['value'];
                }
            }
        }
        return $value;
    }

    // Accessor for description with translation fallback
    public function getDescriptionAttribute($value)
    {
        if ($this->translations->isNotEmpty()) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'description') {
                    return $translation['value'];
                }
            }
        }
        return $value;
    }

    // Accessor to calculate available stock
    public function getItemStockAttribute($value)
    {
        return $value - $this->sell_count > 0 ? $value - $this->sell_count : 0;
    }

    // Accessor for variations with JSON handling
    public function getVariationsAttribute($value)
    {
        try {
            if (is_string($value) && json_decode($value, true) == null && $this->newVariations->isNotEmpty() && $this->newVariationOptions->isNotEmpty()) {
                $result = [];
                foreach ($this->newVariations as $variation) {
                    $variationArray = [
                        "variation_id" => (int) $variation['id'],
                        "name" => $variation['name'],
                        "type" => $variation['type'],
                        "min" => (string) $variation['min'],
                        "max" => (string) $variation['max'],
                        "required" => $variation['is_required'] ? "on" : 'off',
                        "values" => []
                    ];

                    foreach ($this->newVariationOptions as $option) {
                        if ($option['variation_id'] == $variation['id']) {
                            $current_stock = $option['stock_type'] == 'unlimited' ? 'unlimited' : $option['total_stock'] - $option['sell_count'];
                            $variationArray['values'][] = [
                                "label" => $option['option_name'],
                                "optionPrice" => $option['option_price'],
                                "total_stock" => (string) $option['total_stock'],
                                "stock_type" => $option['stock_type'],
                                "sell_count" => (string) $option['sell_count'],
                                "option_id" => (int) $option['id'],
                                "current_stock" => (int) ($current_stock == 'unlimited' ? 0 : max($current_stock, 0)),
                            ];
                        }
                    }
                    $result[] = $variationArray;
                }

                return json_encode($result);
            } else {
                return $value;
            }
        } catch (\Exception $exception) {
            info([$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            return $value;
        }
    }
    public function toSearchableArray()
    {

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'active' => $this->status == 1 && optional($this->restaurant)->status == 1,
            'restaurant_name' => $this->restaurant->name ?? null,  // Safe access
            'restaurant_id' => $this->restaurant->restaurant_id ?? null,  // Safe access
            'zone_id' => $this->restaurant->zone_id ?? null,       // Safe access
            'weekday' => $this->restaurant->weekday ?? null,
            'created_at' => $this->created_at,
            'desc' => $this->desc
        ];
    }
}
