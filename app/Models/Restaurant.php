<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Models\Vendor;
use App\Scopes\ZoneScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\ReportFilter;

class Restaurant extends Model
{

    use HasFactory, ReportFilter;
    protected $fillable = ['food_section', 'status'];

    protected $with = ['restaurant_config'];
    public $incrementing = false;
    protected $primaryKey = 'restaurant_id';
    protected $casts = [
        'minimum_order' => 'float',
        'comission' => 'float',
        'tax' => 'float',
        'delivery_charge' => 'float',
        'schedule_order' => 'boolean',
        'free_delivery' => 'boolean',
        'vendor_id' => 'integer',
        'status' => 'integer',
        'delivery' => 'boolean',
        'take_away' => 'boolean',
        'zone_id' => 'integer',
        'food_section' => 'boolean',
        'reviews_section' => 'boolean',
        'active' => 'boolean',
        'gst_status' => 'boolean',
        'free_delivery_distance_status' => 'boolean',
        'pos_system' => 'boolean',
        'self_delivery_system' => 'integer',
        'open' => 'integer',
        'gst_code' => 'string',
        'free_delivery_distance_value' => 'float',
        'off_day' => 'string',
        'gst' => 'string',
        'free_delivery_distance' => 'string',
        'veg' => 'integer',
        'non_veg' => 'integer',
        'announcement' => 'integer',
        'minimum_shipping_charge' => 'float',
        'per_km_shipping_charge' => 'float',
        'maximum_shipping_charge' => 'float',
        'cuisine_id' => 'integer',
        // 'order_subscription'=>'boolean',
        'order_subscription_active' => 'boolean',
        'opening_time' => 'datetime',
        'closeing_time' => 'datetime',
        'cutlery' => 'boolean',
        'foods_count' => 'integer',
        'reviews_comments_count' => 'integer',
    ];

    protected $appends = ['gst_status', 'gst_code', 'free_delivery_distance_status', 'free_delivery_distance_value', 'logo_full_url', 'cover_photo_full_url', 'meta_image_full_url'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'gst', 'free_delivery_distance'
    ];

    public function getLogoFullUrlAttribute()
    {
        $value = $this->logo;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'logo') {
                    return Helpers::get_full_url('restaurant', $value, $storage['value']);
                }
            }
        }

        return Helpers::get_full_url('restaurant', $value, 'public');
    }
    public function getCoverPhotoFullUrlAttribute()
    {
        $value = $this->cover_photo;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'cover_photo') {
                    return Helpers::get_full_url('restaurant/cover', $value, $storage['value']);
                }
            }
        }

        return Helpers::get_full_url('restaurant/cover', $value, 'public');
    }
    public function getMetaImageFullUrlAttribute()
    {
        $value = $this->meta_image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'meta_image') {
                    return Helpers::get_full_url('restaurant', $value, $storage['value']);
                }
            }
        }

        return Helpers::get_full_url('restaurant', $value, 'public');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class,'restaurant_tag','restaurant_id','tag_id');
    }


    public function characteristics()
    {
        return $this->belongsToMany(Characteristic::class,'characteristic_restaurant','restaurant_id','characteristic_id');
    }

    public function restaurant_config()
    {
        return $this->hasOne(RestaurantConfig::class, 'restaurant_id', 'restaurant_id');
    }


    public function translations()
    {
        return $this->morphMany(Translation::class, 'translationable');
    }
    public function transaction()
    {
        return $this->hasMany(OrderTransaction::class, 'vendor_id', 'vendor_id');
    }
    public function coupon()
    {
        return $this->hasMany(Coupon::class, 'restaurant_id', 'restaurant_id');
    }
    public function notification_setup()
    {
        return $this->hasMany(RestaurantNotificationSetting::class, 'restaurant_id', 'restaurant_id');
    }
    public function coupon_valid()
    {
        return $this->hasMany(Coupon::class, 'restaurant_id')->where('status', 1)->whereDate('expire_date', '>=', date('Y-m-d'))->whereDate('start_date', '<=', date('Y-m-d'));
    }

    public function restaurant_sub()
    {
        return $this->hasOne(RestaurantSubscription::class)->where('status', 1)->latest();
    }

    public function restaurant_subs()
    {
        return $this->hasMany(RestaurantSubscription::class, 'restaurant_id', 'restaurant_id');
    }
    public function restaurant_sub_trans()
    {
        return $this->hasOne(SubscriptionTransaction::class)->latest();
    }
    public function restaurant_sub_update_application()
    {
        return $this->hasOne(RestaurantSubscription::class)->latest();
    }


    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id', 'id');
    }

    public function foods()
    {
        return $this->hasMany(Food::class, 'restaurant_id', 'restaurant_id');
    }
    public function foods_for_reorder()
    {
        return $this->foods()->where('status', 1)->orderby('avg_rating', 'desc')->orderby('recommended', 'desc');
    }

    public function schedules()
    {
        return $this->hasMany(RestaurantSchedule::class,'restaurant_id','restaurant_id')->orderBy('opening_time');
    }

    public function deliverymen()
    {
        return $this->hasMany(DeliveryMan::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class,'restaurant_id','restaurant_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class, 'restaurant_id', 'restaurant_id');
    }

    public function discount()
    {
        return $this->hasOne(Discount::class,'restaurant_id','restaurant_id');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class,'campaign_restaurant','restaurant_id','campaign_id');
    }

    public function itemCampaigns()
    {
        return $this->hasMany(ItemCampaign::class);
    }

    public function reviews()
    {
        return $this->hasManyThrough(
            Review::class,
            Food::class,
            'restaurant_id', // Foreign key on the foods table
            'food_id',       // Foreign key on the reviews table
            'restaurant_id', // Local key on the restaurants table
            'id'
        );
    }
    public function reviews_comments()
    {
        return $this->reviews()->whereNotNull('comment');
    }

    public function getScheduleOrderAttribute($value)
    {
        return (bool)(\App\CentralLogics\Helpers::schedule_order() ? $value : 0);
    }
    public function getRatingAttribute($value)
    {
        $ratings = json_decode($value, true);
        $rating5 = $ratings ? $ratings[5] : 0;
        $rating4 = $ratings ? $ratings[4] : 0;
        $rating3 = $ratings ? $ratings[3] : 0;
        $rating2 = $ratings ? $ratings[2] : 0;
        $rating1 = $ratings ? $ratings[1] : 0;
        return [$rating5, $rating4, $rating3, $rating2, $rating1];
    }

    public function getGstStatusAttribute()
    {
        return (bool)($this->gst ? json_decode($this->gst, true)['status'] : 0);
    }

    public function getGstCodeAttribute()
    {
        return (string)($this->gst ? json_decode($this->gst, true)['code'] : '');
    }

    public function getFreeDeliveryDistanceStatusAttribute()
    {
        return (bool)($this->free_delivery_distance ? json_decode($this->free_delivery_distance, true)['status'] : 0);
    }

    public function getFreeDeliveryDistanceValueAttribute()
    {
        return (string)($this->free_delivery_distance ? json_decode($this->free_delivery_distance, true)['value'] : '');
    }

    public function scopeDelivery($query)
    {
        $query->where('delivery', 1);
    }

    public function scopeTakeaway($query)
    {
        $query->where('take_away', 1);
    }

    public function scopeActive($query)
    {
        if (!\App\CentralLogics\Helpers::commission_check()) {
            $query = $query->where('restaurant_model', '!=', 'commission');
        }
        return $query->where('status', 1);
    }

    public function scopeOpened($query)
    {
        return $query->where('active', 1);
    }

    public function scopeWithOpen($query, $longitude, $latitude)
    {
        $query->selectRaw('*, IF(((select count(*) from `restaurant_schedule` where `restaurants`.`restaurant_id` = `restaurant_schedule`.`restaurant_id` and `restaurant_schedule`.`day` = ' . now()->dayOfWeek . ' and `restaurant_schedule`.`opening_time` < "' . now()->format('H:i:s') . '" and `restaurant_schedule`.`closing_time` >"' . now()->format('H:i:s') . '") > 0), true, false) as open, ST_Distance_Sphere(point(longitude, latitude),point(' . $longitude . ', ' . $latitude . ')) as distance');
    }

    public function scopeWeekday($query)
    {
        return $query->where('off_day', 'not like', "%" . now()->dayOfWeek . "%");
    }


    public function scopeType($query, $type)
    {
        if ($type == 'veg') {
            return $query->where('veg', true);
        } else if ($type == 'non_veg') {
            return $query->where('non_veg', true);
        }

        return $query;
    }
    public function scopeRestaurantModel($query, $type)
    {
        if ($type == 'commission') {
            return $query->where('restaurant_model', 'commission');
        } else if ($type == 'subscribed') {
            return $query->where('restaurant_model', 'subscription');
        } else if ($type == 'unsubscribed') {
            return $query->where('restaurant_model', 'unsubscribed');
        } else if ($type == 'none') {
            return $query->where('restaurant_model', 'none');
        }
        return $query;
    }

    public function scopeCuisine($query, $cuisine_id)
    {
        if ($cuisine_id != 'all') {
            return $query->whereHas('cuisine', function ($query) use ($cuisine_id) {
                $query->where('cuisine_restaurant.cuisine_id', $cuisine_id);
            });
        }
        return $query;
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($restaurant) {
            $restaurant->slug = $restaurant->generateSlug($restaurant->name);
            $restaurant->save();
        });

        static::saved(function ($model) {
            if ($model->isDirty('logo')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->restaurant_id,
                    'key' => 'logo',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($model->isDirty('cover_photo')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->restaurant_id,
                    'key' => 'cover_photo',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if ($model->isDirty('meta_image')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->restaurant_id,
                    'key' => 'meta_image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    private function generateSlug($name)
    {
        $slug = Str::slug($name);
        if ($max_slug = static::where('slug', 'like', "{$slug}%")->latest('restaurant_id')->value('slug')) {

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


    public function cuisine()
    {
        return $this->belongsTo(Cuisine::class,'cuisine_id','id');

    }
    public function disbursement_method()
    {
        return $this->hasOne(DisbursementWithdrawalMethod::class)->where('is_default', 1);
    }
    public function users()
    {
        return $this->morphToMany(User::class, 'visitor_log');
    }

    public function schedule_today()
    {
        return $this->hasMany(RestaurantSchedule::class)->orderBy('opening_time')->where('day', now()->dayOfWeek);
    }


    public function getNameAttribute($value)
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'name') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }
    public function getAddressAttribute($value)
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'address') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }



    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });
        static::addGlobalScope(new ZoneScope);
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }
}
