<?php
/**
 * Network operations using NetworkManager
 */
class Network {
    public function deleteConnection($uuid) {
        $escaped = escapeshellarg($uuid);
        exec("nmcli connection delete uuid $escaped 2>&1", $out, $ret);
        return [
            'message' => $ret === 0 ? "Connection <strong>" . htmlspecialchars($uuid) . "</strong> deleted successfully." : '',
            'error' => $ret !== 0 ? "Failed to delete connection <strong>" . htmlspecialchars($uuid) . "</strong>: " . htmlspecialchars(implode("\n", $out)) : ''
        ];
    }

    public function activateConnection($uuid) {
        $escaped = escapeshellarg($uuid);
        exec("nmcli connection up uuid $escaped 2>&1", $out, $ret);
        return [
            'message' => $ret === 0 ? "Connection <strong>" . htmlspecialchars($uuid) . "</strong> activated successfully." : '',
            'error' => $ret !== 0 ? "Failed to activate connection <strong>" . htmlspecialchars($uuid) . "</strong>: " . htmlspecialchars(implode("\n", $out)) : ''
        ];
    }

    public function connectWifi($ssid, $password) {
        $ssid_arg = escapeshellarg($ssid);
        $cmd = $password !== '' ? 
            "nmcli dev wifi connect $ssid_arg password " . escapeshellarg($password) . " ifname wlan0" :
            "nmcli dev wifi connect $ssid_arg ifname wlan0";
        exec($cmd . " 2>&1", $output, $retval);
        if ($retval === 0) {
            return ['message' => "Connecting to Wi-Fi network <strong>" . htmlspecialchars($ssid) . "</strong>...", 'error' => ''];
        } else {
            exec("nmcli dev disconnect wlan0 2>&1");
            exec("nmcli dev connect wlan0 2>&1");
            exec("sleep 2; nmcli dev wifi rescan 2>&1");
            return ['error' => "Failed to connect to <strong>" . htmlspecialchars($ssid) . "</strong>: " . htmlspecialchars(implode("\n", $output)), 'message' => ''];
        }
    }

    public function rescanWifi() {
        exec("nmcli dev wifi rescan 2>&1");
    }

    public function configureInterface($iface, $mode, $postData) {
        $connectionName = '';
        exec("nmcli -t -f NAME,DEVICE connection show", $connLines);
        foreach ($connLines as $line) {
            list($connName, $dev) = explode(':', $line);
            if ($dev === $iface) {
                $connectionName = $connName;
                break;
            }
        }
        if ($connectionName === '' && in_array($iface, ['eth0', 'usb0'])) {
            $connectionName = $iface;
            exec("nmcli connection add type ethernet ifname $iface con-name $iface autoconnect yes 2>&1");
        }
        if ($connectionName === '') {
            return ['error' => "No connection profile found for interface " . strtoupper($iface) . ".", 'message' => ''];
        }

        $conn_arg = escapeshellarg($connectionName);
        if ($mode === 'dhcp') {
            exec("nmcli connection modify $conn_arg ipv4.method auto ipv4.addresses \"\" ipv4.gateway \"\" ipv4.dns \"\"");
            exec("nmcli connection down $conn_arg", $outDown, $retDown);
            exec("nmcli connection up $conn_arg", $outUp, $retUp);
            return [
                'message' => ($retDown === 0 && $retUp === 0) ? strtoupper($iface) . " set to DHCP (automatic IP)." : '',
                'error' => ($retDown !== 0 || $retUp !== 0) ? "Failed to set DHCP on " . strtoupper($iface) . ": " . htmlspecialchars(implode("\n", array_merge((array)$outDown, (array)$outUp))) : ''
            ];
        } elseif ($mode === 'static') {
            $ip = trim($postData['static_ip']);
            $mask = trim($postData['static_mask']);
            $gw = trim($postData['static_gw']);
            $dns = trim($postData['static_dns']);
            $prefix = 24;
            if ($mask && filter_var($mask, FILTER_VALIDATE_IP)) {
                $long = ip2long($mask);
                if ($long !== false) {
                    $prefix = 32 - log((~$long & 0xFFFFFFFF) + 1, 2);
                    $prefix = intval($prefix);
                }
            }
            $addr_arg = escapeshellarg($ip . "/" . $prefix);
            $gw_arg = escapeshellarg($gw);
            $dns_arg = escapeshellarg($dns);
            $cmdMod = "nmcli connection modify $conn_arg ipv4.method manual ipv4.addresses $addr_arg ipv4.gateway $gw_arg ipv4.dns $dns_arg";
            exec($cmdMod . " 2>&1", $out1, $ret1);
            if ($ret1 === 0) {
                exec("nmcli connection down $conn_arg", $outDown, $retDown);
                exec("nmcli connection up $conn_arg", $outUp, $retUp);
                return [
                    'message' => ($retDown === 0 && $retUp === 0) ? strtoupper($iface) . " configured with static IP " . htmlspecialchars($ip) . "." : '',
                    'error' => ($retDown !== 0 || $retUp !== 0) ? "Failed to apply IP settings on " . strtoupper($iface) . ": " . htmlspecialchars(implode("\n", array_merge((array)$outDown, (array)$outUp))) : ''
                ];
            }
            return ['error' => "Failed to configure static IP on " . strtoupper($iface) . ": " . htmlspecialchars(implode("\n", $out1)), 'message' => ''];
        }
        return ['error' => "Invalid mode for interface $iface.", 'message' => ''];
    }
}
?>
