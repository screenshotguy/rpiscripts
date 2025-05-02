<?php
header('Content-Type: application/json');

$ssid = $_POST['ssid']     ?? '';
$pass = $_POST['password'] ?? '';

if ($ssid === '') {
    http_response_code(400);
    echo '{"error":"Missing SSID"}';
    exit;
}

$ssid_esc = escapeshellarg($ssid);

if ($pass === '') {
    // Open network
    $cmd = "nmcli device wifi connect $ssid_esc";
} else {
    $pass_esc = escapeshellarg($pass);
    $cmd = "nmcli device wifi connect $ssid_esc password $pass_esc";
}

exec($cmd . ' 2>&1', $out, $rc);

echo json_encode([
    'cmd'    => $cmd,
    'output' => implode("\n", $out),
    'status' => $rc
]);

