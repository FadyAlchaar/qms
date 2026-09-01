<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use App\Models\PrinterEvent;
use App\Models\TicketPrinter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PrintJobProcessor
{
    public function __construct(
        private WindowsPrinterService $printerService,
        private PrinterHealthService $healthService
    ) {
    }

    /**
     * Process one pending print job.
     *
     * Returns true when a job was claimed and processed/requeued.
     * Returns false when there are no pending jobs.
     */
    public function processOne(): bool
    {
        /*
         * Atomically claim the oldest pending job.
         *
         * This prevents two workers from processing
         * the same job simultaneously.
         */
        $job = DB::transaction(function () {
            $job = PrintJob::query()
                ->where('status', 'pending')
                ->orderBy('queued_at')
                ->lockForUpdate()
                ->first();

            if (!$job) {
                return null;
            }

            $job->status = 'printing';
            $job->started_at = now();
            $job->attempts++;
            $job->save();

            return $job;
        });

        if (!$job) {
            return false;
        }

        $printer = null;

        try {
            /*
             * Load the currently selected printer.
             */
            $printer = TicketPrinter::query()
                ->findOrFail($job->printer_id);

            if (!$printer->enabled) {
                throw new RuntimeException(
                    "Printer {$printer->id} is disabled."
                );
            }

            /*
             * IMPORTANT:
             *
             * Check the physical printer before sending anything
             * to the Windows spooler.
             *
             * The IP address is used ONLY for this health check.
             */
            if (!$this->healthService->check($printer)) {
                throw new RuntimeException(
                    "Printer {$printer->id} is offline."
                );
            }

            /*
             * Build the printable ticket content.
             *
             * Actual thermal/Arabic formatting will be improved later.
             */
            $payload = $job->payload ?? [];

            $content =
                "QMS\n" .
                "--------------------------\n" .
                "Ticket: " . ($payload['ticket_number'] ?? '') . "\n" .
                "Service: " . ($payload['service_id'] ?? '') . "\n" .
                "--------------------------\n\n";

            /*
             * Send the job through the Windows printer.
             *
             * We deliberately do NOT print directly to the IP.
             */
            $this->printerService->print(
                $printer,
                $content
            );

            /*
             * PRINT SUCCESS
             */
            $job->status = 'completed';
            $job->completed_at = now();
            $job->error_message = null;
            $job->save();

            PrinterEvent::create([
                'printer_id' => $printer->id,
                'event_type' => 'PRINT_SUCCESS',
                'status' => 'ONLINE',
                'message' => 'Print job completed successfully.',
                'ip_address' => $printer->ip_address,
                'response_time_ms' => null,
                'created_at' => now(),
            ]);

            return true;

        } catch (\Throwable $e) {

            /*
             * Record the failure against the current printer.
             */
            if ($printer) {
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
                    'ip_address' => $printer->ip_address,
                    'response_time_ms' => null,
                    'created_at' => now(),
                ]);
            }

            /*
             * PRIMARY PRINTER FAILED
             *
             * Once the primary printer has reached its maximum
             * attempts, try to move the job to its fallback printer.
             */
            if (
                $job->fallback_printer_id &&
                $job->attempts >= $job->max_attempts
            ) {

                $fallbackPrinter = TicketPrinter::query()
                    ->find($job->fallback_printer_id);

                /*
                 * Make sure the fallback printer:
                 *
                 * - exists
                 * - is enabled
                 * - belongs to the same branch
                 */
                if (
                    $fallbackPrinter &&
                    $fallbackPrinter->enabled &&
                    $fallbackPrinter->branch_id === $job->branch_id
                ) {

                    /*
                     * Check the physical fallback printer before
                     * switching the job to it.
                     */
                    $fallbackOnline =
                        $this->healthService->check(
                            $fallbackPrinter
                        );

                    if ($fallbackOnline) {

                        $primaryError = $e->getMessage();

                        /*
                         * Switch the job to the fallback printer.
                         *
                         * Reset attempts so the fallback gets its
                         * own complete retry allowance.
                         */
                        $job->printer_id = $fallbackPrinter->id;
                        $job->attempts = 0;

                        /*
                         * Clear the fallback pointer.
                         *
                         * This prevents recursive fallback chains.
                         */
                        $job->fallback_printer_id = null;

                        $job->status = 'pending';
                        $job->started_at = null;
                        $job->failed_at = null;

                        $job->error_message =
                            'Primary printer failed after '
                            . $job->max_attempts
                            . ' attempts. '
                            . 'Switched to fallback printer '
                            . $fallbackPrinter->id
                            . '. Primary error: '
                            . $primaryError;

                        $job->save();

                        PrinterEvent::create([
                            'printer_id' => $fallbackPrinter->id,
                            'event_type' => 'FALLBACK_SELECTED',
                            'status' => 'ONLINE',
                            'message' =>
                                'Fallback printer selected after '
                                . 'primary printer failure. '
                                . 'Primary printer ID: '
                                . $printer?->id,
                            'ip_address' =>
                                $fallbackPrinter->ip_address,
                            'response_time_ms' => null,
                            'created_at' => now(),
                        ]);

                        /*
                         * The job is now safely queued for
                         * the fallback printer.
                         */
                        return true;
                    }

                    /*
                     * Fallback exists but is also offline.
                     *
                     * Leave the job failed rather than pretending
                     * that the fallback is usable.
                     */
                }
            }

            /*
             * NORMAL RETRY
             *
             * Either:
             *
             * - maximum attempts have not been reached
             * - there is no fallback
             * - fallback is unavailable
             */
            $job->error_message = $e->getMessage();

            if ($job->attempts >= $job->max_attempts) {
                $job->status = 'failed';
                $job->failed_at = now();
            } else {
                $job->status = 'pending';
            }

            $job->save();

            /*
             * Re-throw so the worker command can report the failure.
             */
            throw $e;
        }
    }
}