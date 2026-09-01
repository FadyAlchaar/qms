<?php

namespace App\Console\Commands;

use App\Services\Printing\PrintJobProcessor;
use Illuminate\Console\Command;
use Throwable;

class ProcessPrintJobs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:process-print-jobs
                            {--once : Process one pending print job and exit}
                            {--sleep=1 : Seconds to wait when there are no pending jobs}';

    /**
     * The console command description.
     */
    protected $description = 'Continuously process pending print jobs';

    /**
     * Execute the console command.
     */
    public function handle(
        PrintJobProcessor $processor
    ): int {
        $sleep = max(1, (int) $this->option('sleep'));

        /*
         * --once mode
         *
         * Useful for testing and troubleshooting.
         */
        if ($this->option('once')) {
            try {
                $processed = $processor->processOne();

                if (!$processed) {
                    $this->info('No pending print jobs.');

                    return self::SUCCESS;
                }

                $this->info('Print job processed successfully.');

                return self::SUCCESS;

            } catch (Throwable $e) {
                $this->error(
                    'Print job failed: ' . $e->getMessage()
                );

                return self::FAILURE;
            }
        }

        /*
         * Continuous worker mode.
         */
        $this->info('Print job worker started.');

        while (true) {
            try {
                $processed = $processor->processOne();

                if (!$processed) {
                    sleep($sleep);
                }

            } catch (Throwable $e) {

                $this->error(
                    'Print job failed: ' . $e->getMessage()
                );

                /*
                 * Prevent a failed job from causing
                 * a tight error loop.
                 */
                sleep($sleep);
            }
        }
    }
}