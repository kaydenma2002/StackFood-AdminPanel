<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class Vendor extends Authenticatable
{
    use Notifiable, HasFactory;
    protected $with = ['storage'];
    protected $fillable = ['remember_token'];
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $hidden = [
        'password',
        'auth_token',
        'remember_token',
    ];

    protected $appends = ['image_full_url'];
    protected $storageCache = null;

    // Accessor for the full URL of the vendor's image
    public function getImageFullUrlAttribute()
    {
        if ($this->storageCache === null) {
            $this->storageCache = $this->getCachedStorage();
        }

        $value = $this->image;
        $storage = $this->storageCache;

        foreach ($storage as $item) {
            if ($item['key'] == 'image') {
                return Helpers::get_full_url('vendor', $value, $item['value']);
            }
        }

        return Helpers::get_full_url('vendor', $value, 'public');
    }

    // Caches the storage data for the vendor
    protected function getCachedStorage()
    {
        return Cache::remember("vendor_{$this->id}_storage", 60, function () {
            return $this->storage;
        });
    }

    // Relationships
    public function order_transaction()
    {
        return $this->hasMany(OrderTransaction::class);
    }

    public function todays_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereDate('created_at', now());
    }

    public function this_week_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function this_month_earning()
    {
        return $this->hasMany(OrderTransaction::class)->whereMonth('created_at', date('m'))->whereYear('created_at', date('Y'));
    }

    public function todaysorders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class)->whereDate('orders.created_at', now());
    }

    public function this_week_orders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class)->whereBetween('orders.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
    }

    public function this_month_orders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class)->whereMonth('orders.created_at', date('m'))->whereYear('orders.created_at', date('Y'));
    }

    public function userinfo()
    {
        return $this->hasOne(UserInfo::class, 'vendor_id', 'id');
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, Restaurant::class);
    }

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class, 'vendor_id', 'id');
    }

    public function withdrawrequests()
    {
        return $this->hasMany(WithdrawRequest::class);
    }

    public function wallet()
    {
        return $this->hasOne(RestaurantWallet::class);
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    // Model boot method to handle saving events
    protected static function boot()
    {
        parent::boot();
        static::saved(function ($model) {
            if ($model->isDirty('image')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert(
                    [
                        'data_type' => get_class($model),
                        'data_id' => $model->id,
                        'key' => 'image',
                    ],
                    [
                        'value' => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}
