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

class CallNextService
{
    /**
     * Call the next eligible waiting ticket for a counter session.
     *
     * The complete operation is atomic:
     *
     * 1. Validate the counter session.
     * 2. Find the next eligible waiting ticket.
     * 3. Lock the ticket.
     * 4. Assign employee/counter/session.
     * 5. Change status to called.
     * 6. Record called_at.
     * 7. Create an audit event.
     *
     * This prevents two counters from successfully claiming
     * the same ticket.
     */
    public function callNext(
        int $counterSessionId
    ): ?QueueTicket {
        return DB::transaction(function () use ($counterSessionId) {

            /*
             * ------------------------------------------------------
             * 1. Validate active counter session
             * ------------------------------------------------------
             */
            $session = CounterSession::query()
                ->where('id', $counterSessionId)
                ->where('status', 'ACTIVE')
                ->whereNull('ended_at')
                ->lockForUpdate()
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

            /*
             * ------------------------------------------------------
             * 2. Validate employee
             * ------------------------------------------------------
             */
            $employee = Employee::query()
                ->where('id', $session->employee_id)
                ->where('status', 1)
                ->first();

            if (!$employee) {
                throw new RuntimeException(
                    'The employee assigned to this counter session '
                    . 'is invalid or inactive.'
                );
            }

            /*
             * ------------------------------------------------------
             * 3. Validate counter
             * ------------------------------------------------------
             */
            $counter = Counter::query()
                ->where('id', $session->counter_id)
                ->where('status', 1)
                ->first();

            if (!$counter) {
                throw new RuntimeException(
                    'The counter assigned to this session '
                    . 'is invalid or inactive.'
                );
            }

            /*
             * The employee and counter must belong to the
             * same branch.
             */
            if ((int) $employee->branch_id !== (int) $counter->branch_id) {
                throw new RuntimeException(
                    'The employee and counter do not belong '
                    . 'to the same branch.'
                );
            }

            $branchId = (int) $counter->branch_id;

            /*
             * ------------------------------------------------------
             * 4. Validate employee shift
             * ------------------------------------------------------
             */
            if ($session->employee_shift_id === null) {
                throw new RuntimeException(
                    'The counter session is not associated '
                    . 'with an employee shift.'
                );
            }

            $employeeShift = EmployeeShift::query()
                ->where('id', $session->employee_shift_id)
                ->where('employee_id', $session->employee_id)
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
             * ------------------------------------------------------
             * 5. Load active queue configuration
             * ------------------------------------------------------
             */
            $configuration = QueueConfiguration::query()
                ->where('branch_id', $branchId)
                ->where('status', 1)
                ->first();

            if (!$configuration) {
                throw new RuntimeException(
                    "No active queue configuration exists "
                    . "for branch {$branchId}."
                );
            }

            /*
             * ------------------------------------------------------
             * 6. Find next eligible waiting ticket
             * ------------------------------------------------------
             *
             * IMPORTANT:
             *
             * lockForUpdate() is inside THIS transaction.
             * Therefore another counter cannot safely claim
             * the same row while this transaction is running.
             */
            $query = QueueTicket::query()
                ->where('queue_tickets.branch_id', $branchId)
                ->where('queue_tickets.status', 'waiting');

            /*
             * If services are explicitly assigned to employees,
             * only select tickets for services assigned to this
             * employee.
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
             * Selection order:
             *
             * 1. Highest priority.
             * 2. Oldest issued ticket.
             * 3. Lowest sequence number.
             * 4. Lowest database ID.
             */
            $ticket = $query
                ->orderByDesc('queue_tickets.priority')
                ->orderBy('queue_tickets.issued_at')
                ->orderBy('queue_tickets.sequence_number')
                ->orderBy('queue_tickets.id')
                ->lockForUpdate()
                ->first();

            /*
             * There is simply nobody waiting.
             */
            if (!$ticket) {
                return null;
            }

            /*
             * ------------------------------------------------------
             * 7. Final eligibility validation
             * ------------------------------------------------------
             *
             * Normally the query above already guarantees this.
             * Keeping this check here makes the service safer if
             * selection logic changes later.
             */
            if ((int) $ticket->branch_id !== $branchId) {
                throw new RuntimeException(
                    'The selected ticket does not belong '
                    . 'to the counter branch.'
                );
            }

            /*
             * A ticket selected here MUST still be waiting.
             */
            if ($ticket->status !== 'waiting') {
                throw new RuntimeException(
                    'The selected ticket is no longer waiting.'
                );
            }

            /*
             * ------------------------------------------------------
             * 8. Assign operational responsibility
             * ------------------------------------------------------
             */
            $now = now();

            $ticket->employee_id = $session->employee_id;
            $ticket->counter_id = $session->counter_id;
            $ticket->counter_session_id = $session->id;

            $ticket->status = 'called';
            $ticket->called_at = $now;

            $ticket->save();

            /*
             * ------------------------------------------------------
             * 9. Audit event
             * ------------------------------------------------------
             */
            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'employee_id' => $session->employee_id,
                'counter_id' => $session->counter_id,
                'event_type' => 'called',
                'old_status' => 'waiting',
                'new_status' => 'called',
                'notes' => null,
                'metadata' => [
                    'counter_session_id' => $session->id,
                    'source' => 'call_next',
                ],
            ]);

            /*
             * Return the final database state.
             */
            return $ticket->fresh();
        });
    }
}