<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    protected $fillable = ['message', 'delivered', 'http_status', 'failure_reason'];

    protected $casts = [
        'delivered' => 'boolean',
    ];
}
