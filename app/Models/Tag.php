<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use HasFactory;
    protected $fillable = ['tag'];

    public function items()
    {
        return $this->belongsToMany(Food::class)->using('App\Models\FoodTag');
    }
    public function restaurants()
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_tag', 'tag_id', 'restaurant_id');
    }
}
