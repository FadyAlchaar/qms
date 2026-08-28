<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketIssuancePoint extends Model
{
    protected $fillable = [
        'branch_id',
        'device_id',
        'name',
        'type',
        'printer_id',
        'location',
        'requires_receptionist',
        'allows_priority',
        'enabled',
        'last_used_at',
    ];

    protected $casts = [
        'requires_receptionist' => 'boolean',
        'allows_priority' => 'boolean',
        'enabled' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function printer(): BelongsTo
    {
        return $this->belongsTo(
            TicketPrinter::class,
            'printer_id'
        );
    }
}