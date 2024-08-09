<?php
namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class BusinessSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // Automatically eager-load the storage relation
    public $with = ['storage'];

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }


    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            $value = Helpers::getDisk();

            DB::table('storages')->updateOrInsert([
                'data_type' => get_class($model),
                'data_id' => $model->id,
            ], [
                'value' => $value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
