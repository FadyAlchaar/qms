<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('code', 50);

            $table->string('address')->nullable();
            $table->string('phone', 50)->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique([
                'organization_id',
                'code'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};