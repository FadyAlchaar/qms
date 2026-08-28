<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_issuance_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ticket_id')
                ->constrained('queue_tickets')
                ->cascadeOnDelete();

            $table->foreignId('issuance_point_id')
                ->constrained('ticket_issuance_points')
                ->restrictOnDelete();

            /*
             * Optional receptionist/user who issued it.
             *
             * NULL for kiosk/mobile issuance.
             */
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('source_type', 30);

            /*
             * Information about the issuing device/session.
             */
            $table->string('ip_address', 45)
                ->nullable();

            $table->string('user_agent', 500)
                ->nullable();

            $table->string('request_id', 100)
                ->nullable();

            /*
             * Useful for debugging duplicate requests,
             * especially from mobile/kiosk clients.
             */
            $table->string('idempotency_key', 100)
                ->nullable();

            $table->timestamp('issued_at');

            $table->timestamps();

            $table->index([
                'ticket_id',
                'issued_at'
            ]);

            $table->index([
                'issuance_point_id',
                'issued_at'
            ]);

            $table->index('request_id');

            $table->unique('idempotency_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_issuance_logs');
    }
};