<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketIssuanceLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'issuance_point_id',
        'user_id',
        'source_type',
        'ip_address',
        'user_agent',
        'request_id',
        'idempotency_key',
        'issued_at',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
    ];
}