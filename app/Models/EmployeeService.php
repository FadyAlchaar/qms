<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EmployeeService extends Pivot
{
    use HasFactory;

    protected $table = 'employee_services';

    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'service_id',
    ];
}