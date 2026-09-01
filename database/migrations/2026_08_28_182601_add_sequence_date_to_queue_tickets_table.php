<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->date('sequence_date')->nullable()->after('sequence_number');
        });

        /*
         * Existing tickets must receive their sequence date before
         * we make the new unique constraint.
         */
        DB::statement("
            UPDATE queue_tickets
            SET sequence_date = DATE(issued_at)
            WHERE sequence_date IS NULL
        ");

        /*
         * The old constraint:
         *   branch_id + ticket_number
         *
         * prevented daily ticket numbers from resetting.
         *
         * The new sequence is:
         *   branch + service + date + ticket number
         */
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropUnique('queue_tickets_branch_id_ticket_number_unique');

            $table->unique(
                ['branch_id', 'service_id', 'sequence_date', 'ticket_number'],
                'queue_tickets_sequence_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropUnique('queue_tickets_sequence_unique');
        });

        /*
         * Restore the original uniqueness rule.
         */
        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->unique(
                ['branch_id', 'ticket_number'],
                'queue_tickets_branch_id_ticket_number_unique'
            );
        });

        Schema::table('queue_tickets', function (Blueprint $table) {
            $table->dropColumn('sequence_date');
        });
    }
};