<?php

namespace App\Services\Queue;

use App\Models\QueueTicket;
use App\Models\TicketEvent;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketStateService
{
    /**
     * Allowed ticket state transitions.
     */
    private const TRANSITIONS = [
        'waiting' => [
            'called',
            'cancelled',
        ],

        'called' => [
            'serving',
            'no_show',
        ],

        'serving' => [
            'completed',
            'no_show',
        ],

        'completed' => [],

        'no_show' => [],

        'cancelled' => [],
    ];

    /**
     * Change the state of a ticket.
     */
    public function transition(
        int $ticketId,
        string $newStatus,
        ?int $employeeId = null,
        ?int $counterId = null,
        ?int $counterSessionId = null,
        ?string $notes = null,
        array $metadata = []
    ): QueueTicket {
        return DB::transaction(function () use (
            $ticketId,
            $newStatus,
            $employeeId,
            $counterId,
            $counterSessionId,
            $notes,
            $metadata
        ) {
            $ticket = QueueTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            $oldStatus = $ticket->status;

            $this->validateTransition(
                $oldStatus,
                $newStatus
            );

            $now = now();

            $ticket->status = $newStatus;

            /*
             * Store the responsible employee/counter/session
             * when they are supplied.
             */
            if ($employeeId !== null) {
                $ticket->employee_id = $employeeId;
            }

            if ($counterId !== null) {
                $ticket->counter_id = $counterId;
            }

            if ($counterSessionId !== null) {
                $ticket->counter_session_id = $counterSessionId;
            }

            /*
             * Store lifecycle timestamps.
             */
            switch ($newStatus) {
                case 'called':
                    $ticket->called_at = $now;
                    break;

                case 'serving':
                    $ticket->service_started_at = $now;
                    break;

                case 'completed':
                    $ticket->completed_at = $now;
                    break;

                case 'no_show':
                    $ticket->no_show_at = $now;
                    break;

                case 'cancelled':
                    $ticket->cancelled_at = $now;
                    break;
            }

            if ($notes !== null) {
                $ticket->notes = $notes;
            }

            $ticket->save();

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'employee_id' => $employeeId ?? $ticket->employee_id,
                'counter_id' => $counterId ?? $ticket->counter_id,
                'event_type' => $this->eventTypeForTransition(
                    $oldStatus,
                    $newStatus
                ),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'notes' => $notes,
                'metadata' => $metadata,
            ]);

            return $ticket->fresh();
        });
    }

    /**
     * Recall a called/serving ticket.
     */
    public function recall(
        int $ticketId,
        ?int $employeeId = null,
        ?int $counterId = null,
        ?int $counterSessionId = null,
        ?string $notes = null,
        array $metadata = []
    ): QueueTicket {
        return DB::transaction(function () use (
            $ticketId,
            $employeeId,
            $counterId,
            $counterSessionId,
            $notes,
            $metadata
        ) {
            $ticket = QueueTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            if (!in_array(
                $ticket->status,
                ['called', 'serving'],
                true
            )) {
                throw new RuntimeException(
                    "Ticket {$ticket->id} cannot be recalled "
                    . "while it is '{$ticket->status}'."
                );
            }

            $ticket->recall_count++;

            if ($employeeId !== null) {
                $ticket->employee_id = $employeeId;
            }

            if ($counterId !== null) {
                $ticket->counter_id = $counterId;
            }

            if ($counterSessionId !== null) {
                $ticket->counter_session_id = $counterSessionId;
            }

            if ($notes !== null) {
                $ticket->notes = $notes;
            }

            $ticket->save();

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'employee_id' => $employeeId ?? $ticket->employee_id,
                'counter_id' => $counterId ?? $ticket->counter_id,
                'event_type' => 'recalled',
                'old_status' => $ticket->status,
                'new_status' => $ticket->status,
                'notes' => $notes,
                'metadata' => array_merge(
                    $metadata,
                    [
                        'recall_count' => $ticket->recall_count,
                    ]
                ),
            ]);

            return $ticket->fresh();
        });
    }

    /**
     * Validate whether a state transition is allowed.
     */
    private function validateTransition(
        string $oldStatus,
        string $newStatus
    ): void {
        if ($oldStatus === $newStatus) {
            throw new RuntimeException(
                "Ticket is already in '{$newStatus}' state."
            );
        }

        if (!array_key_exists(
            $oldStatus,
            self::TRANSITIONS
        )) {
            throw new RuntimeException(
                "Unknown current ticket status '{$oldStatus}'."
            );
        }

        if (!in_array(
            $newStatus,
            self::TRANSITIONS[$oldStatus],
            true
        )) {
            throw new RuntimeException(
                "Invalid ticket transition: "
                . "{$oldStatus} → {$newStatus}."
            );
        }
    }

    /**
     * Determine the audit event type.
     */
    private function eventTypeForTransition(
        string $oldStatus,
        string $newStatus
    ): string {
        return match ($newStatus) {
            'called' => 'called',
            'serving' => 'service_started',
            'completed' => 'completed',
            'no_show' => 'no_show',
            'cancelled' => 'cancelled',
            default => 'status_changed',
        };
    }
}