<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('queue_tickets')
                ->cascadeOnDelete();

            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('counter_id')
                ->nullable()
                ->constrained('counters')
                ->nullOnDelete();

            $table->string('event_type', 40);

            $table->string('old_status', 30)
                ->nullable();

            $table->string('new_status', 30)
                ->nullable();

            $table->text('notes')
                ->nullable();

            /*
             * Additional structured information.
             * Examples:
             * - previous counter
             * - new service
             * - recall number
             */
            $table->json('metadata')
                ->nullable();

            $table->timestamp('created_at');

            $table->index([
                'ticket_id',
                'created_at'
            ]);

            $table->index([
                'event_type',
                'created_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
    }
};