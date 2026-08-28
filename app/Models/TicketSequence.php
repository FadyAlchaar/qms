<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketSequence extends Model
{
    protected $fillable = [
        'branch_id',
        'service_id',
        'sequence_date',
        'current_number',
    ];

    protected $casts = [
        'sequence_date' => 'date',
        'current_number' => 'integer',
    ];
}