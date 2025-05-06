<?php
/**
 * Data collection for Wi-Fi networks, saved connections, and interface status
 */
class Data {
    public function getWifiList() {
        $wifi_list = [];
        putenv('LANG=en_US.UTF-8');
        setlocale(LC_ALL, 'en_US.UTF-8');
        exec("LANG=en_US.UTF-8 nmcli -t --escape no -f IN-USE,BSSID,SSID,SECURITY,SIGNAL dev wifi list", $wifi_lines);
        foreach ($wifi_lines as $line) {
            if (substr_count($line, ':') < 8) continue;
            $parts = explode(':', $line);
            $in_use = ($parts[0] === '*');
            $bssid = implode(':', array_slice($parts, 1, 6));
            $ssid = $parts[7];
            $security = $parts[8] ?? '';
            $signal = isset($parts[9]) ? intval($parts[9]) : 0;
            $ssid = trim(preg_replace('/\p{C}/u', '', $ssid));
            $security = trim(preg_replace('/\p{C}/u', '', $security));
            $wifi_list[] = [
                'in_use' => $in_use,
                'bssid' => $bssid,
                'ssid' => $ssid ?: '(hidden)',
                'security' => $security ?: 'OPEN',
                'signal' => $signal
            ];
        }
        return $wifi_list;
    }

    public function getSavedConnections() {
        $saved_connections = [];
        exec("nmcli -t -f NAME,UUID,TYPE,DEVICE connection show", $lines);
        foreach ($lines as $line) {
            $parts = explode(':', $line, 4);
            if (count($parts) === 4) {
                $saved_connections[] = [
                    'name' => $parts[0],
                    'uuid' => $parts[1],
                    'type' => $parts[2],
                    'device' => $parts[3],
                ];
            }
        }
        return $saved_connections;
    }

    public function getInterfaceStatus($interfaces) {
        $iface_status = [];
        foreach ($interfaces as $if) {
            $iface_status[$if] = [
                'state' => 'down',
                'connection' => '',
                'ip' => '',
                'method' => '',
                'mac' => ''
            ];
            $stateLine = [];
            exec("nmcli -t -f DEVICE,STATE,CONNECTION device status | grep ^$if:", $stateLine);
            if (!empty($stateLine)) {
                $fields = explode(':', $stateLine[0]);
                if (count($fields) >= 3) {
                    $iface_status[$if]['state'] = $fields[1];
                    if ($fields[2] !== "--") {
                        $iface_status[$if]['connection'] = $fields[2];
                    }
                }
            }
            $ipLine = [];
            exec("nmcli -t -f IP4.ADDRESS dev show $if | grep IP4.ADDRESS", $ipLine);
            if (!empty($ipLine)) {
                $addr = explode(':', $ipLine[0]);
                if (isset($addr[1])) {
                    $iface_status[$if]['ip'] = explode('/', $addr[1])[0];
                }
            }
            if ($iface_status[$if]['connection'] !== '') {
                $methLine = [];
                $conn_name_arg = escapeshellarg($iface_status[$if]['connection']);
                exec("nmcli -t -f ipv4.method connection show $conn_name_arg", $methLine);
                if (!empty($methLine)) {
                    $methodVal = trim($methLine[0]);
                    $iface_status[$if]['method'] = (strpos($methodVal, 'manual') !== false) ? 'Static' : 'DHCP';
                }
            }
            $macLine = [];
            exec("nmcli -t -f GENERAL.HWADDR dev show $if | grep GENERAL.HWADDR", $macLine);
            if (!empty($macLine)) {
                $macParts = explode(':', $macLine[0], 2);
                if (isset($macParts[1])) {
                    $iface_status[$if]['mac'] = trim($macParts[1]);
                }
            }
        }
        return $iface_status;
    }
}
?>
