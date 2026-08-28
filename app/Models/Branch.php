<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'address',
        'phone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(Counter::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function queueConfiguration(): HasOne
    {
        return $this->hasOne(QueueConfiguration::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function printers(): HasMany
    {
        return $this->hasMany(TicketPrinter::class);
    }

    public function issuancePoints(): HasMany
    {
        return $this->hasMany(TicketIssuancePoint::class);
    }
}