<?php

namespace App\Services\Queue;

use App\Models\Counter;
use App\Models\CounterSession;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\QueueConfiguration;
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

            /*
             * Every operational transition must be performed
             * by a valid employee/counter/session combination.
             *
             * Cancellation of a waiting ticket is intentionally
             * allowed without operational context.
             */
            if (in_array(
                $newStatus,
                ['called', 'serving', 'completed', 'no_show'],
                true
            )) {
                $this->validateOperationalContext(
                    $ticket,
                    $employeeId,
                    $counterId,
                    $counterSessionId
                );
            }

            /*
             * If the ticket is already assigned to an employee,
             * counter or session, don't allow another operational
             * context to take control of it.
             */
            if (in_array(
                $newStatus,
                ['serving', 'completed', 'no_show'],
                true
            )) {
                $this->validateExistingAssignment(
                    $ticket,
                    $employeeId,
                    $counterId,
                    $counterSessionId
                );
            }

            $now = now();

            $ticket->status = $newStatus;

            /*
             * Store operational responsibility.
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

            /*
             * Recall is an operational action.
             */
            $this->validateOperationalContext(
                $ticket,
                $employeeId,
                $counterId,
                $counterSessionId
            );

            /*
             * The employee/counter/session performing the recall
             * must be the same operational context currently
             * responsible for the ticket.
             */
            $this->validateExistingAssignment(
                $ticket,
                $employeeId,
                $counterId,
                $counterSessionId
            );

            /*
             * Load active queue configuration.
             */
            $configuration = $this->getActiveConfiguration(
                $ticket->branch_id
            );

            if (!$configuration->allow_recall) {
                throw new RuntimeException(
                    'Ticket recall is disabled.'
                );
            }

            if (
                $ticket->recall_count >=
                $configuration->recall_count
            ) {
                throw new RuntimeException(
                    'Maximum recall count has been reached.'
                );
            }

            $ticket->recall_count++;

            if ($notes !== null) {
                $ticket->notes = $notes;
            }

            $ticket->save();

            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'employee_id' => $employeeId,
                'counter_id' => $counterId,
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
     * Validate employee/counter/session operational context.
     */
    private function validateOperationalContext(
        QueueTicket $ticket,
        ?int $employeeId,
        ?int $counterId,
        ?int $counterSessionId
    ): void {
        /*
         * All three identifiers are mandatory.
         */
        if (
            $employeeId === null ||
            $counterId === null ||
            $counterSessionId === null
        ) {
            throw new RuntimeException(
                'Employee, counter, and counter session '
                . 'are all required for this operation.'
            );
        }

        /*
         * Employee must exist, be active, and belong to
         * the same branch as the ticket.
         */
        $employee = Employee::query()
            ->where('id', $employeeId)
            ->where('branch_id', $ticket->branch_id)
            ->where('status', 1)
            ->first();

        if (!$employee) {
            throw new RuntimeException(
                'The selected employee is invalid, inactive, '
                . 'or does not belong to the ticket branch.'
            );
        }

        /*
         * Counter must exist, be active, and belong to
         * the same branch as the ticket.
         */
        $counter = Counter::query()
            ->where('id', $counterId)
            ->where('branch_id', $ticket->branch_id)
            ->where('status', 1)
            ->first();

        if (!$counter) {
            throw new RuntimeException(
                'The selected counter is invalid, inactive, '
                . 'or does not belong to the ticket branch.'
            );
        }

        /*
         * Counter session must be active and belong to
         * the specified employee and counter.
         */
        $session = CounterSession::query()
            ->where('id', $counterSessionId)
            ->where('employee_id', $employeeId)
            ->where('counter_id', $counterId)
            ->where('status', 'ACTIVE')
            ->whereNull('ended_at')
            ->first();

        if (!$session) {
            throw new RuntimeException(
                'The counter session is invalid, inactive, '
                . 'or does not belong to the selected employee '
                . 'and counter.'
            );
        }

        /*
         * The session's employee shift must belong to the
         * same employee.
         */
        if ($session->employee_shift_id === null) {
            throw new RuntimeException(
                'The counter session is not associated '
                . 'with an employee shift.'
            );
        }

        $employeeShift = EmployeeShift::query()
            ->where('id', $session->employee_shift_id)
            ->where('employee_id', $employeeId)
            ->where('status', 'ACTIVE')
            ->whereDate('date', now()->toDateString())
            ->first();

        if (!$employeeShift) {
            throw new RuntimeException(
                'The employee does not have an active shift '
                . 'for today.'
            );
        }

        /*
         * Check employee/service eligibility.
         */
        $configuration = $this->getActiveConfiguration(
            $ticket->branch_id
        );

        if ($configuration->employee_service_mode === 'ASSIGNED') {
            $assigned = DB::table('employee_services')
                ->where('employee_id', $employeeId)
                ->where('service_id', $ticket->service_id)
                ->exists();

            if (!$assigned) {
                throw new RuntimeException(
                    'The selected employee is not assigned '
                    . 'to this service.'
                );
            }
        }
    }

    /**
     * Prevent another employee/counter/session from taking
     * over a ticket already assigned to an operational context.
     */
    private function validateExistingAssignment(
        QueueTicket $ticket,
        int $employeeId,
        int $counterId,
        int $counterSessionId
    ): void {
        if (
            $ticket->employee_id !== null &&
            $ticket->employee_id !== $employeeId
        ) {
            throw new RuntimeException(
                'This ticket is already assigned to another employee.'
            );
        }

        if (
            $ticket->counter_id !== null &&
            $ticket->counter_id !== $counterId
        ) {
            throw new RuntimeException(
                'This ticket is already assigned to another counter.'
            );
        }

        if (
            $ticket->counter_session_id !== null &&
            $ticket->counter_session_id !== $counterSessionId
        ) {
            throw new RuntimeException(
                'This ticket is already assigned to another counter session.'
            );
        }
    }

    /**
     * Get the active queue configuration for a branch.
     */
    private function getActiveConfiguration(
        int $branchId
    ): QueueConfiguration {
        $configuration = QueueConfiguration::query()
            ->where('branch_id', $branchId)
            ->where('status', 1)
            ->first();

        if (!$configuration) {
            throw new RuntimeException(
                "No active queue configuration exists for branch "
                . "{$branchId}."
            );
        }

        return $configuration;
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