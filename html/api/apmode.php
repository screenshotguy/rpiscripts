<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

putenv('LANG=en_US.UTF-8');  setlocale(LC_ALL,'en_US.UTF-8');
$if = 'wlan0';
$ssid = 'StartSmart';
$psk  = 'smartstart';
$addr = '192.168.4.1/24';
$gw   = '192.168.4.1';

$cmds = [
  // Make sure Wi-Fi is on and stop whatever is active
  "nmcli -t -g WIFI g || true",
  "nmcli radio wifi on",
  "nmcli device disconnect $if 2>/dev/null || true",

  // Delete any old copy of the connection (ignore errors)
  "nmcli connection delete '$ssid' 2>/dev/null || true",

  // Create the AP profile
  "nmcli connection add type wifi ifname $if con-name '$ssid' ssid '$ssid'",

  // Configure it as an access-point with WPA-PSK + static/shared IPv4
  "nmcli connection modify '$ssid' \
       802-11-wireless.mode ap \
       802-11-wireless.band bg \
       802-11-wireless-security.key-mgmt wpa-psk \
       802-11-wireless-security.psk '$psk' \
       ipv4.addresses $addr \
       ipv4.gateway $gw \
       ipv4.method shared",

  // Bring it up
  "nmcli connection up '$ssid'"
];

$out = []; $rc = 0;
foreach ($cmds as $c) {
    exec($c, $out[], $rc);
    if ($rc !== 0) break;               // stop on first failure
}

echo json_encode([
  'cmds'   => $cmds,
  'output' => $out,
  'status' => $rc
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

