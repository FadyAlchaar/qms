<?php

namespace App\Services\Queue;

use App\Models\CounterSession;
use App\Models\QueueConfiguration;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QueueSelectionService
{
    /**
     * Select the next waiting ticket for an active counter session.
     *
     * Selection rules:
     * 1. Ticket must be waiting.
     * 2. Ticket must belong to the same branch.
     * 3. Employee/service eligibility is respected.
     * 4. Higher priority tickets are selected first.
     * 5. Older tickets are selected first when priority is equal.
     * 6. ID is used as a deterministic final tie-breaker.
     *
     * The ticket is locked inside the transaction so competing
     * counters cannot safely claim the same ticket.
     */
    public function selectNext(
        int $counterSessionId
    ): ?QueueTicket {
        return DB::transaction(function () use ($counterSessionId) {

            $session = CounterSession::query()
                ->where('id', $counterSessionId)
                ->where('status', 'ACTIVE')
                ->whereNull('ended_at')
                ->first();

            if (!$session) {
                throw new RuntimeException(
                    'The counter session is invalid or inactive.'
                );
            }

            if ($session->employee_id === null) {
                throw new RuntimeException(
                    'The counter session has no employee assigned.'
                );
            }

            if ($session->counter_id === null) {
                throw new RuntimeException(
                    'The counter session has no counter assigned.'
                );
            }

            $configuration = QueueConfiguration::query()
                ->where('branch_id', $this->getBranchId($session))
                ->where('status', 1)
                ->first();

            if (!$configuration) {
                throw new RuntimeException(
                    'No active queue configuration exists for this branch.'
                );
            }

            $query = QueueTicket::query()
                ->where('queue_tickets.branch_id', $configuration->branch_id)
                ->where('queue_tickets.status', 'waiting');

            /*
             * In ASSIGNED mode, the employee can only serve
             * services explicitly assigned to them.
             */
            if ($configuration->employee_service_mode === 'ASSIGNED') {
                $query->whereExists(function ($subQuery) use ($session) {
                    $subQuery
                        ->select(DB::raw(1))
                        ->from('employee_services')
                        ->whereColumn(
                            'employee_services.service_id',
                            'queue_tickets.service_id'
                        )
                        ->where(
                            'employee_services.employee_id',
                            $session->employee_id
                        );
                });
            }

            /*
             * Priority:
             *   higher priority first
             *
             * FIFO:
             *   earlier issued ticket first
             *
             * Deterministic fallback:
             *   lower sequence number, then lower ID.
             */
            $ticket = $query
                ->orderByDesc('queue_tickets.priority')
                ->orderBy('queue_tickets.issued_at')
                ->orderBy('queue_tickets.sequence_number')
                ->orderBy('queue_tickets.id')
                ->lockForUpdate()
                ->first();

            return $ticket;
        });
    }

    /**
     * Get the branch associated with the counter session.
     */
    private function getBranchId(
        CounterSession $session
    ): int {
        $branchId = DB::table('counters')
            ->where('id', $session->counter_id)
            ->value('branch_id');

        if ($branchId === null) {
            throw new RuntimeException(
                'The counter session counter does not belong to a valid branch.'
            );
        }

        return (int) $branchId;
    }
}