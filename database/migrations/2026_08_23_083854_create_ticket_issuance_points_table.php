<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_issuance_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('device_id')
                ->nullable()
                ->constrained('devices')
                ->nullOnDelete();

            $table->string('name');

            /*
             * RECEPTION
             * KIOSK
             * MOBILE
             * API
             */
            $table->string('type', 30);

            /*
             * Optional printer assigned specifically
             * to this issuance point.
             */
            $table->foreignId('printer_id')
                ->nullable()
                ->constrained('ticket_printers')
                ->nullOnDelete();

            $table->string('location')
                ->nullable();

            $table->boolean('requires_receptionist')
                ->default(false);

            $table->boolean('allows_priority')
                ->default(false);

            $table->boolean('enabled')
                ->default(true);

            $table->timestamp('last_used_at')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'branch_id',
                'name'
            ]);

            $table->index([
                'branch_id',
                'type',
                'enabled'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_issuance_points');
    }
};