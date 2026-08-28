<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();

            /*
             * Where the ticket belongs.
             */
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            /*
             * The requested service.
             */
            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();

            /*
             * Human-readable ticket number.
             *
             * Examples:
             * A001
             * REG-025
             * C-104
             */
            $table->string('ticket_number', 30);

            /*
             * Numeric sequence used internally.
             */
            $table->unsignedInteger('sequence_number');

            /*
             * Queue lifecycle.
             */
            $table->string('status', 30)
                ->default('WAITING');

            /*
             * Priority level.
             *
             * 0 = normal
             * higher number = higher priority
             */
            $table->unsignedInteger('priority')
                ->default(0);

            /*
             * Who created the ticket.
             *
             * Can be NULL because a citizen may obtain
             * a ticket through QR/mobile without logging in.
             */
            $table->string('source', 30)
                ->default('RECEPTION');

            /*
             * Optional anonymous/citizen reference.
             *
             * We deliberately do NOT require personal information
             * just to enter the queue.
             */
            $table->string('citizen_reference', 100)
                ->nullable();

            /*
             * Important timestamps for reporting.
             */
            $table->timestamp('issued_at');

            $table->timestamp('called_at')
                ->nullable();

            $table->timestamp('service_started_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamp('no_show_at')
                ->nullable();

            $table->timestamp('cancelled_at')
                ->nullable();

            /*
             * Which employee/counter eventually handled it.
             */
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->nullOnDelete();

            $table->foreignId('counter_id')
                ->nullable()
                ->constrained('counters')
                ->nullOnDelete();

            /*
             * Counter session that handled this ticket.
             *
             * This gives us an exact historical connection
             * between ticket -> employee -> counter session.
             */
            $table->foreignId('counter_session_id')
                ->nullable()
                ->constrained('counter_sessions')
                ->nullOnDelete();

            /*
             * Number of times the ticket was recalled.
             */
            $table->unsignedInteger('recall_count')
                ->default(0);

            /*
             * Optional notes.
             */
            $table->text('notes')
                ->nullable();

            $table->timestamps();

            /*
             * A ticket number must be unique within a branch.
             */
            $table->unique([
                'branch_id',
                'ticket_number'
            ]);

            /*
             * Useful indexes for the queue engine.
             */
            $table->index([
                'branch_id',
                'service_id',
                'status'
            ]);

            $table->index([
                'branch_id',
                'status',
                'priority',
                'issued_at'
            ]);

            $table->index([
                'employee_id',
                'status'
            ]);

            $table->index([
                'counter_id',
                'status'
            ]);

            $table->index('issued_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};