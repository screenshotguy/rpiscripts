<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
$ssid = $_POST['ssid'] ?? '';
if($ssid===''){http_response_code(400);echo'{"error":"ssid required"}';exit;}

$cmd = "nmcli connection delete id ".escapeshellarg($ssid);
exec($cmd.' 2>&1',$out,$rc);
echo json_encode(['cmd'=>$cmd,'output'=>implode("\n",$out),'status'=>$rc],
                 JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);

