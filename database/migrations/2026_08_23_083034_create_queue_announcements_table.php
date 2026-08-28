<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_announcements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('ticket_id')
                ->nullable()
                ->constrained('queue_tickets')
                ->nullOnDelete();

            $table->foreignId('counter_id')
                ->nullable()
                ->constrained('counters')
                ->nullOnDelete();

            $table->string('ticket_number', 30);

            $table->string('announcement_type', 30)
                ->default('CALL');

            $table->string('message')
                ->nullable();

            $table->unsignedInteger('display_duration')
                ->default(10);

            $table->timestamp('announced_at');

            $table->timestamps();

            $table->index([
                'branch_id',
                'announced_at'
            ]);

            $table->index([
                'ticket_id',
                'announced_at'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_announcements');
    }
};