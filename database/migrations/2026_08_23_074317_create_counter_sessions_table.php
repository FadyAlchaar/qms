<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counter_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('counter_id')
                ->constrained('counters')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->constrained('employees')
                ->cascadeOnDelete();

            $table->foreignId('employee_shift_id')
                ->nullable()
                ->constrained('employee_shifts')
                ->nullOnDelete();

            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();

            $table->string('status', 30)->default('ACTIVE');

            $table->timestamps();

            $table->index([
                'counter_id',
                'status'
            ]);

            $table->index([
                'employee_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counter_sessions');
    }
};