<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVisibility extends Model
{
    protected $fillable = [

        'product_id',

        'audience',

        'required_badges',

        'visibility_unlocked',

        'unlocked_at',

    ];


    protected $casts = [

        'visibility_unlocked' => 'boolean',

        'unlocked_at' => 'datetime',

    ];


    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }
}