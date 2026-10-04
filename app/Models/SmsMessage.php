<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    use BelongsToUser;

    protected $fillable = ['message', 'delivered', 'http_status', 'failure_reason'];

    protected $casts = [
        'delivered' => 'boolean',
    ];
}
