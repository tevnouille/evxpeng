<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelPrice extends Model
{
    protected $fillable = ['date', 'essence_price', 'diesel_price'];

    protected $casts = [
        'date' => 'date',
        'essence_price' => 'decimal:3',
        'diesel_price' => 'decimal:3',
    ];
}
