<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_configurations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            /*
             * employee_service_mode:
             *
             * ALL       = every active employee can serve every service
             * ASSIGNED  = employee must be assigned to the service
             */
            $table->string('employee_service_mode', 20)
                ->default('ALL');

            /*
             * Ticket numbering:
             *
             * DAILY  = sequence resets every day
             * CONTINUOUS = sequence continues indefinitely
             */
            $table->string('ticket_number_mode', 20)
                ->default('DAILY');

            $table->unsignedInteger('daily_ticket_start')
                ->default(1);

            $table->unsignedInteger('max_waiting_tickets')
                ->default(0);

            $table->unsignedInteger('max_daily_tickets')
                ->default(0);

            $table->boolean('allow_priority')
                ->default(false);

            $table->boolean('allow_recall')
                ->default(true);

            $table->unsignedInteger('recall_count')
                ->default(2);

            $table->unsignedInteger('recall_delay_seconds')
                ->default(5);

            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            $table->unique('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_configurations');
    }
};