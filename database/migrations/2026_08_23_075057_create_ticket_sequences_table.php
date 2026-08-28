<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sequences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            $table->date('sequence_date');

            $table->unsignedInteger('current_number')
                ->default(0);

            $table->timestamps();

            $table->unique([
                'branch_id',
                'service_id',
                'sequence_date'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sequences');
    }
};