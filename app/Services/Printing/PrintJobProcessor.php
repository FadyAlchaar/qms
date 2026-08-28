<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\PrinterEvent;
use App\Models\TicketPrinter;
use RuntimeException;

class PrintJobProcessor
{
    public function __construct(
        private WindowsPrinterService $printerService
    ) {
    }

    /**
     * Process one pending print job.
     *
     * Returns true when a job was processed,
     * false when there are no pending jobs.
     */
    public function processOne(): bool
    {
        $job = PrintJob::query()
            ->where('status', 'pending')
            ->orderBy('queued_at')
            ->first();

        if (!$job) {
            return false;
        }

        $job->status = 'printing';
        $job->started_at = now();
        $job->attempts++;
        $job->save();

        try {
            $printer = TicketPrinter::query()
                ->findOrFail($job->printer_id);

            if (!$printer->enabled) {
                throw new RuntimeException(
                    "Printer {$printer->id} is disabled."
                );
            }

            $payload = $job->payload ?? [];

            $content =
                "QMS\n" .
                "--------------------------\n" .
                "Ticket: " . ($payload['ticket_number'] ?? '') . "\n" .
                "Service: " . ($payload['service_id'] ?? '') . "\n" .
                "--------------------------\n\n";

            $this->printerService->print(
                $printer,
                $content
            );

            $job->status = 'completed';
            $job->completed_at = now();
            $job->error_message = null;
            $job->save();

            $printer->status = 'ONLINE';
            $printer->last_checked_at = now();
            $printer->last_online_at = now();
            $printer->last_error = null;
            $printer->save();

            PrinterEvent::create([
                'printer_id' => $printer->id,
                'event_type' => 'PRINT_SUCCESS',
                'status' => 'ONLINE',
                'message' => 'Print job completed successfully.',
                'ip_address' => null,
                'response_time_ms' => null,
                'created_at' => now(),
            ]);

            return true;

        } catch (\Throwable $e) {

            $job->error_message = $e->getMessage();

            if ($job->attempts >= $job->max_attempts) {
                $job->status = 'failed';
                $job->failed_at = now();
            } else {
                $job->status = 'pending';
            }

            $job->save();

            if (isset($printer)) {
                $printer->status = 'OFFLINE';
                $printer->last_checked_at = now();
                $printer->last_offline_at = now();
                $printer->last_error = $e->getMessage();
                $printer->save();

                PrinterEvent::create([
                    'printer_id' => $printer->id,
                    'event_type' => 'PRINT_FAILED',
                    'status' => 'OFFLINE',
                    'message' => $e->getMessage(),
                    'ip_address' => null,
                    'response_time_ms' => null,
                    'created_at' => now(),
                ]);
            }

            throw $e;
        }
    }
}