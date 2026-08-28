<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('printer_id')
                ->constrained('ticket_printers')
                ->restrictOnDelete();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('queue_tickets')
                ->nullOnDelete();

            $table->string('job_type', 30)
                ->default('TICKET');

            $table->string('status', 30)
                ->default('PENDING');

            $table->unsignedInteger('attempts')
                ->default(0);

            $table->unsignedInteger('max_attempts')
                ->default(3);

            $table->longText('payload')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->timestamp('queued_at');

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamp('failed_at')
                ->nullable();

            $table->foreignId('fallback_printer_id')
                ->nullable()
                ->constrained('ticket_printers')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'branch_id',
                'status',
                'queued_at'
            ]);

            $table->index([
                'printer_id',
                'status'
            ]);

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
    }
};