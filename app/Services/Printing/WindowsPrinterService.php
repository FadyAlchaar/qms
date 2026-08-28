<?php

namespace App\Services\Printing;

use App\Models\TicketPrinter;
use RuntimeException;

class WindowsPrinterService
{
    /**
     * Send plain text to a Windows printer through the Windows print spooler.
     *
     * The printer is addressed by its Windows printer name, not by IP.
     * Windows handles the actual network printer connection.
     */
    public function print(TicketPrinter $printer, string $content): void
    {
        $printerName = $printer->windows_printer_name;

        if (!$printerName) {
            throw new RuntimeException(
                'The printer has no Windows printer name configured.'
            );
        }

        $ps1 = sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'qms-print-' . uniqid() . '.ps1';

            try {
                if (file_put_contents(
                    $ps1,
                    $this->buildPowerShellScript($printerName, $content)
                ) === false) {
                    throw new RuntimeException(
                        'Unable to create the temporary PowerShell script.'
                    );
                }

            $command =
                'powershell.exe -NoProfile -ExecutionPolicy Bypass -File '
                . escapeshellarg($ps1);

            $output = [];
            $resultCode = -1;

            exec($command, $output, $resultCode);

            if ($resultCode !== 0) {
                $details = trim(implode(PHP_EOL, $output));

                throw new RuntimeException(
                    'Windows printer command failed.'
                    . ($details !== '' ? ' ' . $details : '')
                );
            }
        } finally {
            @unlink($ps1);
        }
    }

    private function buildPowerShellScript(
        string $printerName,
        string $content
    ): string {
        $escapedPrinter = str_replace("'", "''", $printerName);
        $escapedContent = str_replace("'", "''", $content);

        return "'{$escapedContent}' | Out-Printer "
            . "-Name '{$escapedPrinter}'"
            . PHP_EOL;
    }
}