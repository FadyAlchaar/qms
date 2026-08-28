<?php

namespace App\Services\Queue;

use App\Models\PrintJob;
use App\Models\QueueConfiguration;
use App\Models\QueueTicket;
use App\Models\TicketEvent;
use App\Models\TicketIssuanceLog;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketIssuanceService
{
    public function __construct(
        private TicketNumberService $ticketNumberService
    ) {
    }

    /**
     * Issue a new queue ticket.
     *
     * The ticket, event, issuance log and print job are created
     * inside one database transaction.
     */
    public function issue(
        int $branchId,
        int $serviceId,
        int $issuancePointId,
        string $sourceType = 'reception',
        ?int $userId = null,
        ?string $citizenReference = null,
        int $priority = 0,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $requestId = null,
        ?string $idempotencyKey = null
    ): QueueTicket {
        return DB::transaction(function () use (
            $branchId,
            $serviceId,
            $sourceType,
            $issuancePointId,
            $userId,
            $citizenReference,
            $priority,
            $ipAddress,
            $userAgent,
            $requestId,
            $idempotencyKey
        ) {

            /*
             * Idempotency protection.
             *
             * If the same request arrives twice because of a network
             * retry, we return the original ticket instead of issuing
             * another one.
             */
            if ($idempotencyKey !== null) {
                $existingLog = TicketIssuanceLog::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existingLog) {
                    return QueueTicket::query()
                        ->findOrFail($existingLog->ticket_id);
                }
            }

            /*
             * Lock the branch configuration while issuing.
             */
            $configuration = QueueConfiguration::query()
                ->where('branch_id', $branchId)
                ->where('status', 1)
                ->lockForUpdate()
                ->first();

            if (!$configuration) {
                throw new RuntimeException(
                    "No active queue configuration exists for branch {$branchId}."
                );
            }

            /*
             * Validate priority.
             */
            if ($priority > 0 && !$configuration->allow_priority) {
                throw new RuntimeException(
                    'Priority tickets are not enabled for this branch.'
                );
            }

            /*
             * Services are organization-level, not branch-level.
             *
             * Therefore we verify the service exists and is active.
             */
            $service = DB::table('services')
                ->where('id', $serviceId)
                ->where('status', 1)
                ->first();

            if (!$service) {
                throw new RuntimeException(
                    'The selected service is not available.'
                );
            }

            /*
             * Make sure the service belongs to the same organization
             * as the branch.
             */
            $branch = DB::table('branches')
                ->where('id', $branchId)
                ->first();

            if (!$branch) {
                throw new RuntimeException(
                    "Branch {$branchId} does not exist."
                );
            }

            if ($service->organization_id !== $branch->organization_id) {
                throw new RuntimeException(
                    'The selected service does not belong to this organization.'
                );
            }

            /*
             * Check maximum active queue size.
             */
            if ($configuration->max_waiting_tickets !== null) {

                $activeCount = QueueTicket::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('status', [
                        'waiting',
                        'called',
                        'serving',
                    ])
                    ->count();

                if (
                    $activeCount >=
                    $configuration->max_waiting_tickets
                ) {
                    throw new RuntimeException(
                        'The maximum queue capacity has been reached.'
                    );
                }
            }

            /*
             * Generate the next ticket number.
             */
            $number = $this->ticketNumberService->next(
                $branchId,
                $serviceId
            );

            /*
             * Create the ticket.
             */
            $ticket = QueueTicket::create([
                'branch_id' => $branchId,
                'service_id' => $serviceId,
                'ticket_number' => $number['ticket_number'],
                'sequence_number' => $number['sequence_number'],
                'status' => 'waiting',
                'priority' => $priority,
                'source' => $sourceType,
                'citizen_reference' => $citizenReference,
                'issued_at' => now(),
                'recall_count' => 0,
            ]);

            /*
             * Record immutable ticket history.
             */
            TicketEvent::create([
                'ticket_id' => $ticket->id,
                'employee_id' => null,
                'counter_id' => null,
                'event_type' => 'issued',
                'old_status' => null,
                'new_status' => 'waiting',
                'notes' => null,
                'metadata' => [
                    'source' => $sourceType,
                ],
            ]);

            /*
             * Record issuance audit information.
             */
            TicketIssuanceLog::create([
                'ticket_id' => $ticket->id,
                'issuance_point_id' => $issuancePointId,
                'user_id' => $userId,
                'source_type' => $sourceType,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_id' => $requestId,
                'idempotency_key' => $idempotencyKey,
                'issued_at' => now(),
            ]);

            /*
        * Resolve the printer assigned to this issuance point.
        */
        $printerId = null;

        if ($issuancePointId !== null) {
            $issuancePoint = \App\Models\TicketIssuancePoint::query()
                ->where('id', $issuancePointId)
                ->where('branch_id', $branchId)
                ->where('enabled', 1)
                ->first();

            if (!$issuancePoint) {
                throw new RuntimeException(
                    'The selected ticket issuance point is not available.'
                );
            }

            $printer = $issuancePoint->printer;

            if (!$printer || !$printer->enabled) {
                throw new RuntimeException(
                    'No enabled printer is assigned to the selected ticket issuance point.'
                );
            }

            $printerId = $printer->id;
        }

        /*
        * Create the server-side print job.
        *
        * The actual printer will NOT be contacted here.
        */
        PrintJob::create([
            'branch_id' => $branchId,
            'printer_id' => $printerId,
            'ticket_id' => $ticket->id,
            'job_type' => 'ticket',
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3,
            'payload' => [
                'ticket_id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'service_id' => $serviceId,
            ],
            'queued_at' => now(),
            'fallback_printer_id' => null,
        ]);

            return $ticket->fresh();
        });
    }
}