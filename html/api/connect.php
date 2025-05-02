<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('X-Accel-Buffering: no');         // nginx: disable buffering
@ob_flush(); @flush();                   // send headers

$ssid = $_POST['ssid']     ?? '';
$pass = $_POST['password'] ?? '';

if($ssid===''){http_response_code(400);echo"Missing SSID\n";exit;}

$ssid_esc = escapeshellarg($ssid);
$cmd = ($pass==='')
      ? "nmcli --wait 20 device wifi connect $ssid_esc"
      : "nmcli --wait 20 device wifi connect $ssid_esc password ".escapeshellarg($pass);

$proc = popen($cmd.' 2>&1','r');
while(!feof($proc)){
    echo rtrim(fgets($proc))."\n";
    @ob_flush(); @flush();               // stream line to client
}
$pstat = pclose($proc);

