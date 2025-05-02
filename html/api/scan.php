<?php
declare(strict_types=1);

// Always tell the client we’re sending UTF-8 JSON
header('Content-Type: application/json; charset=utf-8');

// Make sure child processes (nmcli) also speak UTF-8
putenv('LANG=en_US.UTF-8');
setlocale(LC_ALL, 'en_US.UTF-8');

// ---------- run the scan ----------
$raw = shell_exec('nmcli -t -f SSID,SECURITY,SIGNAL,CHAN dev wifi list');
$aps = [];

foreach (explode("\n", trim($raw)) as $line) {
    if ($line === '') continue;
    [$ssid, $sec, $sig, $chan] = array_map('trim', explode(':', $line));

    // If the SSID isn’t valid UTF-8, guess-convert it
    if (!mb_check_encoding($ssid, 'UTF-8')) {
        $ssid = mb_convert_encoding($ssid, 'UTF-8', 'ISO-8859-1,Windows-1252');
    }

    $aps[] = [
        'ssid' => $ssid ?: '(hidden)',
        'sec'  => $sec ?: 'OPEN',
        'sig'  => (int)$sig,
        'chan' => $chan,
    ];
}

// Send nice-looking JSON without \u escapes
echo json_encode($aps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

