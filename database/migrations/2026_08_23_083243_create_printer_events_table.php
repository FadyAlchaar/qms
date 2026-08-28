<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('printer_id')
                ->constrained('ticket_printers')
                ->cascadeOnDelete();

            $table->string('event_type', 40);

            $table->string('status', 30)
                ->nullable();

            $table->text('message')
                ->nullable();

            $table->string('ip_address', 45)
                ->nullable();

            $table->unsignedInteger('response_time_ms')
                ->nullable();

            $table->timestamp('created_at');

            $table->index([
                'printer_id',
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
        Schema::dropIfExists('printer_events');
    }
};