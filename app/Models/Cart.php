<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;

    protected $casts = [
        'user_id' => 'integer',
        'item_id' => 'integer',
        'is_guest' => 'boolean',
        'price' => 'float',
        'quantity' => 'integer',
        'add_on_qtys' => 'array',
        'add_on_ids' => 'array',
        'variation' => 'array',
    ];

    protected $fillable = [
        'user_id',
        'item_id',
        'is_guest',
        'add_on_ids',
        'add_on_qtys',
        'item_type',
        'price',
        'quantity',
        'variation',
    ];

    public function item()
    {
        return $this->morphTo();
    }
    public function getAddonsAttribute()
    {
        $addOnIds = json_decode($this->add_on_ids, true);  // Assuming it's stored as JSON

        // If add_on_ids is an array, retrieve the corresponding AddOn records
        return AddOn::whereIn('id', $addOnIds)->get();
    }
}
