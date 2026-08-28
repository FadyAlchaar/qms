<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrinterEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'printer_id',
        'event_type',
        'status',
        'message',
        'ip_address',
        'response_time_ms',
        'created_at',
    ];

    protected $casts = [
        'response_time_ms' => 'integer',
        'created_at' => 'datetime',
    ];
}