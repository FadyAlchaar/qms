<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ticket_id',
        'employee_id',
        'counter_id',
        'event_type',
        'old_status',
        'new_status',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}