<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
putenv('LANG=en_US.UTF-8'); setlocale(LC_ALL,'en_US.UTF-8');

$if   = 'wlan0';
$ssid = trim(shell_exec("nmcli -t -f ACTIVE,SSID dev wifi | grep '^yes' | cut -d: -f2"));
$ip   = trim(shell_exec("ip -4 -o addr show $if | awk '{print \$4}'"));
$gw   = trim(shell_exec("ip route | awk '/default/ && \$5==\"$if\" {print \$3}'"));
$link = shell_exec("iw dev $if link");
preg_match('/signal: (-?\d+) dBm/',$link,$m); $sig = $m[1] ?? '';
$state= trim(shell_exec("nmcli -t -f STATE g"));
$host = trim(shell_exec('hostname'));
$mac  = trim(shell_exec("cat /sys/class/net/$if/address"));

echo json_encode([
  'ssid'=>$ssid,
  'ip'  =>$ip,
  'gw'  =>$gw,
  'sig' =>$sig,
  'state'=>$state,
  'host'=>$host,
  'mac' =>$mac
],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

