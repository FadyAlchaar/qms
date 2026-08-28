<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('name');
            $table->unsignedInteger('number');

            $table->text('description')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique([
                'branch_id',
                'number'
            ]);

            $table->index([
                'branch_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('counters');
    }
};