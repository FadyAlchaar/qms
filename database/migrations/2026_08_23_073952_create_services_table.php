<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('category_id')
                ->nullable()
                ->constrained('service_categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('code', 50);
            $table->string('prefix', 10);

            $table->text('description')->nullable();

            $table->unsignedInteger('estimated_duration')->nullable();

            $table->unsignedInteger('priority')->default(0);

            $table->unsignedInteger('sort_order')->default(0);

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique([
                'organization_id',
                'code'
            ]);

            $table->index([
                'organization_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};