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
    protected $signature = 'app:process-print-jobs';

    /**
     * The console command description.
     */
    protected $description = 'Process one pending print job';

    /**
     * Execute the console command.
     */
    public function handle(
        PrintJobProcessor $processor
    ): int {
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
}