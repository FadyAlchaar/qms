<?php

namespace App\Services\Queue;

use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CounterWorkflowService
{
    public function __construct(
        private CallNextService $callNextService,
        private TicketStateService $ticketStateService
    ) {
    }

    /**
     * Call the next waiting ticket.
     */
    public function callNext(int $counterSessionId): ?QueueTicket
    {
        return $this->callNextService->callNext($counterSessionId);
    }

    /**
     * Start serving the current ticket.
     */
    public function startServing(
        int $counterSessionId,
        ?string $notes = null
    ): QueueTicket {
        $session = $this->getActiveSession($counterSessionId);

        $ticket = $this->getCurrentTicket($counterSessionId);

        if (!$ticket) {
            throw new RuntimeException(
                'There is no called ticket assigned to this counter.'
            );
        }

        if ($ticket->status !== 'called') {
            throw new RuntimeException(
                "The current ticket cannot be started while it is "
                . "'{$ticket->status}'."
            );
        }

        return $this->ticketStateService->transition(
            $ticket->id,
            'serving',
            (int) $session->employee_id,
            (int) $session->counter_id,
            (int) $session->id,
            $notes,
            [
                'source' => 'counter_workflow',
            ]
        );
    }

    /**
     * Complete the current serving ticket.
     */
    public function complete(
        int $counterSessionId,
        ?string $notes = null
    ): QueueTicket {
        $session = $this->getActiveSession($counterSessionId);

        $ticket = $this->getCurrentTicket($counterSessionId);

        if (!$ticket) {
            throw new RuntimeException(
                'There is no active ticket assigned to this counter.'
            );
        }

        if ($ticket->status !== 'serving') {
            throw new RuntimeException(
                "The current ticket cannot be completed while it is "
                . "'{$ticket->status}'."
            );
        }

        return $this->ticketStateService->transition(
            $ticket->id,
            'completed',
            (int) $session->employee_id,
            (int) $session->counter_id,
            (int) $session->id,
            $notes,
            [
                'source' => 'counter_workflow',
            ]
        );
    }

    /**
     * Mark the current ticket as no-show.
     */
    public function noShow(
        int $counterSessionId,
        ?string $notes = null
    ): QueueTicket {
        $session = $this->getActiveSession($counterSessionId);

        $ticket = $this->getCurrentTicket($counterSessionId);

        if (!$ticket) {
            throw new RuntimeException(
                'There is no active ticket assigned to this counter.'
            );
        }

        if (!in_array(
            $ticket->status,
            ['called', 'serving'],
            true
        )) {
            throw new RuntimeException(
                "The current ticket cannot be marked as no-show "
                . "while it is '{$ticket->status}'."
            );
        }

        return $this->ticketStateService->transition(
            $ticket->id,
            'no_show',
            (int) $session->employee_id,
            (int) $session->counter_id,
            (int) $session->id,
            $notes,
            [
                'source' => 'counter_workflow',
            ]
        );
    }

    /**
     * Recall the current ticket.
     */
    public function recall(
        int $counterSessionId,
        ?string $notes = null
    ): QueueTicket {
        $session = $this->getActiveSession($counterSessionId);

        $ticket = $this->getCurrentTicket($counterSessionId);

        if (!$ticket) {
            throw new RuntimeException(
                'There is no active ticket assigned to this counter.'
            );
        }

        return $this->ticketStateService->recall(
            $ticket->id,
            (int) $session->employee_id,
            (int) $session->counter_id,
            (int) $session->id,
            $notes,
            [
                'source' => 'counter_workflow',
            ]
        );
    }

    /**
     * Get the ticket currently assigned to this counter session.
     */
    public function getCurrentTicket(
        int $counterSessionId
    ): ?QueueTicket {
        $session = $this->getActiveSession($counterSessionId);

        return QueueTicket::query()
            ->where('counter_session_id', $session->id)
            ->whereIn('status', ['called', 'serving'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Get the current queue status for the counter's branch.
     */
    public function getQueueStatus(
        int $counterSessionId
    ): array {
        $session = $this->getActiveSession($counterSessionId);

        $counter = Counter::query()
            ->where('id', $session->counter_id)
            ->where('status', 1)
            ->first();

        if (!$counter) {
            throw new RuntimeException(
                'The counter assigned to this session is invalid or inactive.'
            );
        }

        $waitingCount = QueueTicket::query()
            ->where('branch_id', $counter->branch_id)
            ->where('status', 'waiting')
            ->count();

        $currentTicket = $this->getCurrentTicket(
            $counterSessionId
        );

        return [
            'counter_session_id' => $session->id,
            'counter_id' => $counter->id,
            'waiting_count' => $waitingCount,
            'current_ticket' => $currentTicket,
        ];
    }

    /**
     * Get and validate an active counter session.
     */
    private function getActiveSession(
        int $counterSessionId
    ): CounterSession {
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

        return $session;
    }
}