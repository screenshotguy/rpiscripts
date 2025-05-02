<?php
header('Content-Type: application/json');

$cmd   = 'nmcli -t -f SSID,SECURITY,SIGNAL,CHAN device wifi list 2>/dev/null';
$lines = array_filter(explode("\n", shell_exec($cmd)));

$out = [];
foreach ($lines as $l) {
    [$ssid, $sec, $sig, $chan] = array_map('trim', explode(':', $l));
    // Skip hidden SSIDs
    if ($ssid === '') continue;
    $out[] = [
        'ssid'  => $ssid,
        'sec'   => $sec ?: 'OPEN',
        'sig'   => (int)$sig,
        'chan'  => $chan
    ];
}
echo json_encode($out, JSON_UNESCAPED_SLASHES);

