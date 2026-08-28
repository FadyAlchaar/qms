<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_printers', function (Blueprint $table) {
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
             * The exact Windows-installed printer name.
             *
             * Example:
             * Rongta RP332
             * Reception Thermal Printer
             */
            $table->string('windows_printer_name');

            /*
             * Network information is used for health checking only.
             * It is NOT the printing destination.
             */
            $table->string('ip_address', 45)
                ->nullable();

            $table->unsignedSmallInteger('port')
                ->default(9100);

            /*
             * Optional fallback printer.
             *
             * If this printer fails health checking,
             * the print job can be redirected to this printer.
             */
            $table->foreignId('fallback_printer_id')
                ->nullable()
                ->constrained('ticket_printers')
                ->nullOnDelete();

            $table->string('printer_type', 30)
                ->default('THERMAL');

            $table->boolean('enabled')
                ->default(true);

            $table->boolean('is_default')
                ->default(false);

            $table->string('status', 30)
                ->default('UNKNOWN');

            $table->timestamp('last_checked_at')
                ->nullable();

            $table->timestamp('last_online_at')
                ->nullable();

            $table->timestamp('last_offline_at')
                ->nullable();

            $table->text('last_error')
                ->nullable();

            $table->timestamps();

            $table->unique([
                'branch_id',
                'name'
            ]);

            $table->index([
                'branch_id',
                'enabled',
                'status'
            ]);

            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_printers');
    }
};