<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueConfiguration extends Model
{
    protected $fillable = [
        'branch_id',
        'employee_service_mode',
        'ticket_number_mode',
        'daily_ticket_start',
        'max_waiting_tickets',
        'max_daily_tickets',
        'allow_priority',
        'allow_recall',
        'recall_count',
        'recall_delay_seconds',
        'status',
    ];

    protected $casts = [
        'daily_ticket_start' => 'integer',
        'max_waiting_tickets' => 'integer',
        'max_daily_tickets' => 'integer',
        'allow_priority' => 'boolean',
        'allow_recall' => 'boolean',
        'recall_count' => 'integer',
        'recall_delay_seconds' => 'integer',
    ];
}