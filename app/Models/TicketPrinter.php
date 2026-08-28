<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPrinter extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'device_id',
        'name',
        'windows_printer_name',
        'ip_address',
        'port',
        'fallback_printer_id',
        'printer_type',
        'enabled',
        'is_default',
        'status',
        'last_checked_at',
        'last_online_at',
        'last_offline_at',
        'last_error',
    ];

    protected $casts = [
        'port' => 'integer',
        'enabled' => 'boolean',
        'is_default' => 'boolean',
        'last_checked_at' => 'datetime',
        'last_online_at' => 'datetime',
        'last_offline_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}