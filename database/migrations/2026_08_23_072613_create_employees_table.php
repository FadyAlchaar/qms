<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_id')
                ->constrained('organizations')
                ->cascadeOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('employee_number', 50);
            $table->string('name');

            $table->string('phone', 50)->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique([
                'organization_id',
                'employee_number'
            ]);

            $table->index([
                'branch_id',
                'status'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};