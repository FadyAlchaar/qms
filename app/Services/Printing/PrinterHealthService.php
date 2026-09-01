<?php

namespace App\Services\Printing;

use App\Models\PrinterEvent;
use App\Models\TicketPrinter;
use Throwable;

class PrinterHealthService
{
    /**
     * Check whether the printer's TCP port is reachable.
     *
     * This is a health check only.
     * It does NOT perform printing.
     */
    public function check(TicketPrinter $printer): bool
    {
        $ip = $printer->ip_address;
        $port = $printer->port ?: 9100;

        if (!$ip) {
            $this->markOffline(
                $printer,
                'Printer IP address is not configured.'
            );

            return false;
        }

        $start = microtime(true);

        $socket = @fsockopen(
            $ip,
            $port,
            $errno,
            $errstr,
            2
        );

        $responseTime = (int) round(
            (microtime(true) - $start) * 1000
        );

        if ($socket !== false) {
            fclose($socket);

            $printer->status = 'ONLINE';
            $printer->last_checked_at = now();
            $printer->last_online_at = now();
            $printer->last_error = null;
            $printer->save();

            PrinterEvent::create([
                'printer_id' => $printer->id,
                'event_type' => 'HEALTH_CHECK',
                'status' => 'ONLINE',
                'message' => 'Printer TCP port is reachable.',
                'ip_address' => $ip,
                'response_time_ms' => $responseTime,
                'created_at' => now(),
            ]);

            return true;
        }

        $message =
            'TCP connection failed.'
            . ' Error ' . $errno
            . ': ' . $errstr;

        $this->markOffline(
            $printer,
            $message,
            $responseTime
        );

        return false;
    }

    /**
     * Mark a printer offline and record the event.
     */
    private function markOffline(
        TicketPrinter $printer,
        string $message,
        ?int $responseTime = null
    ): void {
        $printer->status = 'OFFLINE';
        $printer->last_checked_at = now();
        $printer->last_offline_at = now();
        $printer->last_error = $message;
        $printer->save();

        PrinterEvent::create([
            'printer_id' => $printer->id,
            'event_type' => 'HEALTH_CHECK',
            'status' => 'OFFLINE',
            'message' => $message,
            'ip_address' => $printer->ip_address,
            'response_time_ms' => $responseTime,
            'created_at' => now(),
        ]);
    }
}