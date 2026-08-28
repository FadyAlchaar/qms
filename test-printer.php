<?php

$ps1 = __DIR__ . DIRECTORY_SEPARATOR . 'test-printer.ps1';

file_put_contents($ps1, <<<'PS1'
'QMS PHP TEST - Ticket 001' | Out-Printer -Name 'XP-80C'
PS1);

exec('powershell.exe -NoProfile -ExecutionPolicy Bypass -File ' . escapeshellarg($ps1), $output, $resultCode);

echo "Exit code: {$resultCode}\r\n";

if (!empty($output)) {
    echo implode("\r\n", $output) . "\r\n";
}

if ($resultCode === 0) {
    echo "PRINT COMMAND SENT\r\n";
} else {
    echo "PRINT FAILED\r\n";
}