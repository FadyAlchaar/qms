<?php

namespace App\Services\Queue;

use App\Models\QueueConfiguration;
use App\Models\TicketSequence;
use Illuminate\Support\Carbon;
use RuntimeException;

class TicketNumberService
{
    /**
     * Generate the next ticket number for a branch/service.
     *
     * This method must be executed inside a database transaction.
     */
    public function next(
        int $branchId,
        int $serviceId
    ): array {
        $configuration = QueueConfiguration::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->first();

        if (!$configuration) {
            throw new RuntimeException(
                "No active queue configuration exists for branch {$branchId}."
            );
        }

        $today = Carbon::today();

        /*
         * Lock today's sequence row.
         *
         * This prevents two kiosks/receptionists from receiving
         * the same sequence number simultaneously.
         */
        $sequence = TicketSequence::query()
            ->where('branch_id', $branchId)
            ->where('service_id', $serviceId)
            ->whereDate('sequence_date', $today)
            ->lockForUpdate()
            ->first();

        /*
         * Create today's sequence if this is the first ticket
         * for this service today.
         */
        if (!$sequence) {
            TicketSequence::query()->insertOrIgnore([
                'branch_id' => $branchId,
                'service_id' => $serviceId,
                'sequence_date' => $today->toDateString(),
                'current_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = TicketSequence::query()
                ->where('branch_id', $branchId)
                ->where('service_id', $serviceId)
                ->whereDate('sequence_date', $today)
                ->lockForUpdate()
                ->first();

            if (!$sequence) {
                throw new RuntimeException(
                    'Unable to initialize the ticket sequence.'
                );
            }
        }

        $nextNumber = $sequence->current_number + 1;

        /*
         * Respect the branch's configured daily limit.
         */
        if (
            $configuration->max_daily_tickets !== null &&
            $nextNumber > $configuration->max_daily_tickets
        ) {
            throw new RuntimeException(
                'The daily ticket limit has been reached.'
            );
        }

        $sequence->update([
            'current_number' => $nextNumber,
        ]);

        /*
         * Build the ticket's displayed number.
         *
         * For now the ticket number is numeric.
         *
         * Example:
         * 001
         * 002
         * 003
         *
         * The actual formatting can later be controlled
         * by ticket_number_mode.
         */
        $ticketNumber = str_pad(
            (string) $nextNumber,
            3,
            '0',
            STR_PAD_LEFT
        );

        return [
            'sequence_number' => $nextNumber,
            'ticket_number' => $ticketNumber,
            'sequence_date' => $today->toDateString(),
        ];
    }
}