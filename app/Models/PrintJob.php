<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintJob extends Model
{
    protected $fillable = [
        'branch_id',
        'printer_id',
        'ticket_id',
        'job_type',
        'status',
        'attempts',
        'max_attempts',
        'payload',
        'error_message',
        'queued_at',
        'started_at',
        'completed_at',
        'failed_at',
        'fallback_printer_id',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'payload' => 'array',
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}