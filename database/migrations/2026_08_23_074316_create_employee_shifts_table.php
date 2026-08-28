<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->cascadeOnDelete();

            $table->date('date');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->string('status', 30)->default('SCHEDULED');

            $table->timestamps();

            $table->unique([
                'employee_id',
                'shift_id',
                'date'
            ]);

            $table->index([
                'shift_id',
                'date',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
    }
};