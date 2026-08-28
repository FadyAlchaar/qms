<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_transfers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('queue_tickets')
                ->cascadeOnDelete();

            $table->foreignId('from_service_id')
                ->constrained('services')
                ->restrictOnDelete();

            $table->foreignId('to_service_id')
                ->constrained('services')
                ->restrictOnDelete();

            $table->foreignId('from_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('to_employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('from_counter_id')
                ->nullable()
                ->constrained('counters')
                ->nullOnDelete();

            $table->foreignId('to_counter_id')
                ->nullable()
                ->constrained('counters')
                ->nullOnDelete();

            $table->text('reason')
                ->nullable();

            $table->timestamp('transferred_at');

            $table->timestamps();

            $table->index([
                'ticket_id',
                'transferred_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_transfers');
    }
};