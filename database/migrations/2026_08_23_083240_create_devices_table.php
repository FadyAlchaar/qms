<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('device_code', 100);

            $table->string('device_type', 30);

            $table->string('ip_address', 45)
                ->nullable();

            $table->string('mac_address', 17)
                ->nullable();

            $table->string('location')
                ->nullable();

            $table->timestamp('last_seen_at')
                ->nullable();

            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            $table->unique([
                'branch_id',
                'device_code'
            ]);

            $table->index([
                'branch_id',
                'device_type',
                'status'
            ]);

            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};