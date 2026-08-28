<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'category_id',
        'name',
        'code',
        'prefix',
        'description',
        'estimated_duration',
        'priority',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'estimated_duration' => 'integer',
        'priority' => 'integer',
        'sort_order' => 'integer',
        'status' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            ServiceCategory::class,
            'category_id'
        );
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            Employee::class,
            'employee_services'
        );
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}