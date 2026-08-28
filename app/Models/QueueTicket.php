<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueTicket extends Model
{
    protected $fillable = [
        'branch_id',
        'service_id',
        'ticket_number',
        'sequence_number',
        'status',
        'priority',
        'source',
        'citizen_reference',
        'issued_at',
        'called_at',
        'service_started_at',
        'completed_at',
        'no_show_at',
        'cancelled_at',
        'employee_id',
        'counter_id',
        'counter_session_id',
        'recall_count',
        'notes',
    ];

    protected $casts = [
        'priority' => 'integer',
        'sequence_number' => 'integer',
        'recall_count' => 'integer',
        'issued_at' => 'datetime',
        'called_at' => 'datetime',
        'service_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'no_show_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];
}