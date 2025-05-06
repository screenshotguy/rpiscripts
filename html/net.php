<?php
mb_internal_encoding('UTF-8');
// *** NetworkManager Web UI for Raspberry Pi ***
// Configuration: hardcoded credentials for login
$username = 'admin';
$password = 'changeme';  // change this password for production use

session_start();  // Start session for auth control :contentReference[oaicite:11]{index=11}

// Handle logout requests
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: {$_SERVER['PHP_SELF']}");
    exit;
}

// Handle login form submission
$login_error = '';
if (isset($_POST['login_username']) && isset($_POST['login_password'])) {
    if ($_POST['login_username'] === $username && $_POST['login_password'] === $password) {
        $_SESSION['logged_in'] = true;
        // Redirect after successful login to avoid re-posting credentials
        header("Location: {$_SERVER['PHP_SELF']}");
        exit;
    } else {
        $login_error = "Invalid username or password.";
    }
}

// If not logged in, display login form and stop
if (empty($_SESSION['logged_in'])) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>Network Manager Login</title>';
    // Include Bootstrap 5 CSS (via CDN) for styling :contentReference[oaicite:12]{index=12}
    echo '<link href="/css/bootstrap.min.css" rel="stylesheet">';
echo '<style>
    table th, table td {
        vertical-align: middle !important;
    }
    .form-check-inline {
        margin-right: 0.5rem;
    }
    .form-control-sm {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
</style>';

    echo '</head><body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">';
    echo '<div class="card shadow-sm p-4" style="min-width:300px;">';
    echo '<h4 class="mb-3">Login</h4>';
    if ($login_error) {
        // Show error message if login failed
        echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($login_error) . '</div>';
    }
    // Login form
    echo '<form method="post">';
    echo '<div class="mb-3"><label for="username" class="form-label">Username</label>';
    echo '<input type="text" class="form-control" id="username" name="login_username" required></div>';
    echo '<div class="mb-3"><label for="password" class="form-label">Password</label>';
    echo '<input type="password" class="form-control" id="password" name="login_password" required></div>';
    echo '<button type="submit" class="btn btn-primary w-100">Login</button>';
    echo '</form>';
    echo '</div></body></html>';
    exit;
}

// *** Main Application (Authenticated) ***

$message = '';  // success message to display
$error   = '';  // error message to display

// Handle deletion of saved NetworkManager connections
if (isset($_POST['delete_connection'])) {
    $connNameToDelete = $_POST['delete_connection'];
    // Safely escape to avoid shell injection
    $escaped = escapeshellarg($connNameToDelete);

    // Run nmcli to delete the connection
    exec("nmcli connection delete uuid $escaped 2>&1", $delOut, $delRet);
    if ($delRet === 0) {
        $message = "Connection <strong>" . htmlspecialchars($connNameToDelete) . "</strong> deleted successfully.";
    } else {
        $error = "Failed to delete connection <strong>" . htmlspecialchars($connNameToDelete) . "</strong>: " 
               . htmlspecialchars(implode("\n", $delOut));
    }
}

// Handle Wi-Fi connect action
if (isset($_POST['connect_ssid'])) {
    $ssid = $_POST['connect_ssid'];
    $wifi_password = $_POST['wifi_password'] ?? '';
    // Construct nmcli command to connect to Wi-Fi
    $ssid_arg = escapeshellarg($ssid);
    if ($wifi_password !== '') {
        $pass_arg = escapeshellarg($wifi_password);
        $cmd = "nmcli dev wifi connect $ssid_arg password $pass_arg ifname wlan0";
    } else {
        $cmd = "nmcli dev wifi connect $ssid_arg ifname wlan0";
    }
    exec($cmd . " 2>&1", $output, $retval);
    if ($retval === 0) {
        $message = "Connecting to Wi-Fi network <strong>" . htmlspecialchars($ssid) . "</strong>...";
    } else {
        $error = "Failed to connect to <strong>" . htmlspecialchars($ssid) . "</strong>: " 
               . htmlspecialchars(implode("\n", $output));
    }
}

// Handle Wi-Fi scan refresh action
if (isset($_POST['scan_refresh'])) {
    exec("nmcli dev wifi rescan");  // trigger rescan of Wi-Fi networks
    // No explicit message; the updated list will be shown
}

// Handle interface IP configuration form (DHCP or Static)
if (isset($_POST['iface']) && isset($_POST['ip_mode'])) {
    $iface = $_POST['iface'];
    $mode  = $_POST['ip_mode'];
    // Identify the NetworkManager connection profile for this interface
    $connectionName = '';
    exec("nmcli -t -f NAME,DEVICE connection show", $connLines);
    foreach ($connLines as $line) {
        list($connName, $dev) = explode(':', $line);
        if ($dev === $iface) {
            $connectionName = $connName;
            break;
        }
    }
    // If no profile is found (disconnected interface), create a default one for wired interfaces
    if ($connectionName === '') {
        if ($iface === 'eth0') {
            $connectionName = 'eth0';
            exec("nmcli connection add type ethernet ifname eth0 con-name eth0 autoconnect yes 2>&1");
        } else if ($iface === 'usb0') {
            $connectionName = 'usb0';
            exec("nmcli connection add type ethernet ifname usb0 con-name usb0 autoconnect yes 2>&1");
        } else if ($iface === 'wlan0') {
            // Cannot set IP on wlan0 without an active connection (skip)
            $connectionName = '';
        }
    }
    if ($connectionName !== '') {
        // Apply DHCP or Static configuration via nmcli
        $conn_arg = escapeshellarg($connectionName);
        if ($mode === 'dhcp') {
            // Switch to DHCP (automatic)
            exec("nmcli connection modify $conn_arg ipv4.method auto ipv4.addresses \"\" ipv4.gateway \"\" ipv4.dns \"\"");
            // Bring interface down and up to obtain a new DHCP lease:contentReference[oaicite:13]{index=13}
            exec("nmcli connection down $conn_arg", $outDown, $retDown);
            exec("nmcli connection up $conn_arg", $outUp, $retUp);
            if ($retDown === 0 && $retUp === 0) {
                $message = strtoupper($iface) . " set to DHCP (automatic IP).";
            } else {
                $error = "Failed to set DHCP on " . strtoupper($iface) . ": " 
                       . htmlspecialchars(implode("\n", array_merge((array)$outDown, (array)$outUp)));
            }
        } else if ($mode === 'static') {
            // Gather static IP settings from form
            $ip   = trim($_POST['static_ip']);
            $mask = trim($_POST['static_mask']);
            $gw   = trim($_POST['static_gw']);
            $dns  = trim($_POST['static_dns']);
            // Convert netmask to CIDR prefix (e.g. 255.255.255.0 -> 24)
            $prefix = 24;
            if ($mask && filter_var($mask, FILTER_VALIDATE_IP)) {
                $long = ip2long($mask);
                if ($long !== false) {
                    // Calculate prefix length from netmask bitwise
                    $prefix = 32 - log((~$long & 0xFFFFFFFF) + 1, 2);
                    $prefix = intval($prefix);
                }
            }
            // Build nmcli command to set static IPv4 config
            $addr_arg = escapeshellarg($ip . "/" . $prefix);
            $gw_arg   = escapeshellarg($gw);
            $dns_arg  = escapeshellarg($dns);
            $cmdMod = "nmcli connection modify $conn_arg ipv4.method manual ipv4.addresses $addr_arg ipv4.gateway $gw_arg ipv4.dns $dns_arg";
            exec($cmdMod . " 2>&1", $out1, $ret1);
            if ($ret1 === 0) {
                // Restart interface to apply static settings:contentReference[oaicite:14]{index=14}
                exec("nmcli connection down $conn_arg", $outDown, $retDown);
                exec("nmcli connection up $conn_arg", $outUp, $retUp);
                if ($retDown === 0 && $retUp === 0) {
                    $message = strtoupper($iface) . " configured with static IP " . htmlspecialchars($ip) . ".";
                } else {
                    $error = "Failed to apply IP settings on " . strtoupper($iface) . ": " 
                           . htmlspecialchars(implode("\n", array_merge((array)$outDown, (array)$outUp)));
                }
            } else {
                $error = "Failed to configure static IP on " . strtoupper($iface) . ": " 
                       . htmlspecialchars(implode("\n", $out1));
            }
        }
    } else {
        $error = "No connection profile found for interface " . strtoupper($iface) . ".";
    }
}

// Gather Wi-Fi network list via nmcli
// Ensure PHP and child processes use UTF-8
mb_internal_encoding('UTF-8');
putenv('LANG=en_US.UTF-8');
setlocale(LC_ALL, 'en_US.UTF-8');

// Gather Wi-Fi network list via nmcli
$wifi_list = [];

exec("LANG=en_US.UTF-8 nmcli -t --escape no -f IN-USE,BSSID,SSID,SECURITY,SIGNAL dev wifi list", $wifi_lines);


foreach ($wifi_lines as $line) {
    // Skip empty or malformed lines
    if (substr_count($line, ':') < 8) continue;

    $parts = explode(':', $line);
    $in_use = ($parts[0] === '*');

    // BSSID is always 6 segments: parts[1] to parts[6]
    $bssid = implode(':', array_slice($parts, 1, 6));

    // The rest are SSID, SECURITY, SIGNAL
    $ssid = $parts[7];
    $security = $parts[8] ?? '';
    $signal = isset($parts[9]) ? intval($parts[9]) : 0;

    // Clean up text
    $ssid = trim(preg_replace('/[^\\x20-\\x7E]/', '', $ssid));
    $security = trim(preg_replace('/[^\\x20-\\x7E]/', '', $security));

    $wifi_list[] = [
        'in_use'   => $in_use,
        'bssid'    => $bssid,
        'ssid'     => $ssid ?: '(hidden)',
        'security' => $security ?: 'OPEN',
        'signal'   => $signal
    ];
}

// Collect saved connections with more info
$saved_connections = [];
exec("nmcli -t -f NAME,UUID,TYPE,DEVICE connection show", $savedConnectionsOut);
foreach ($savedConnectionsOut as $line) {
    $parts = explode(':', $line, 4);
    if (count($parts) === 4) {
        $saved_connections[] = [
            'name'  => $parts[0],
            'uuid'  => $parts[1],
            'type'  => $parts[2],
            'device'=> $parts[3],
        ];
    }
}

// -- Saved Connections Card --
echo '<div class="card mb-4">';
echo '  <div class="card-header">Saved Connections</div>';
echo '  <div class="card-body p-0">';
if (empty($saved_connections)) {
    echo '<div class="p-3 text-muted">No saved connections found.</div>';
} else {
    echo '<div class="table-responsive">';
    echo '<table class="table table-sm table-hover mb-0">';
    echo '<thead><tr><th>Name</th><th>UUID</th><th>Type</th><th>Device</th><th>Action</th></tr></thead><tbody>';
    foreach ($saved_connections as $conn) {
        echo '<tr>';
        echo '  <td>' . htmlspecialchars($conn['name']) . '</td>';
        echo '  <td style="font-family: monospace;">' . htmlspecialchars($conn['uuid']) . '</td>';
        echo '  <td>' . htmlspecialchars($conn['type']) . '</td>';
        echo '  <td>' . ($conn['device'] ? htmlspecialchars($conn['device']) : '-') . '</td>';
        echo '  <td>';
        echo '    <form method="post" class="m-0">';
        echo '      <input type="hidden" name="delete_connection" value="' . htmlspecialchars($conn['uuid']) . '">';
        echo '      <button type="submit" class="btn btn-sm btn-danger">Delete</button>';
        echo '    </form>';
        echo '  </td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}
echo '  </div>';
echo '</div>';



// Gather interface statuses for wlan0, eth0, usb0
$interfaces = ['wlan0', 'eth0', 'usb0'];
$iface_status = [];
foreach ($interfaces as $if) {
    // Initialize status
    $iface_status[$if] = [
        'state'      => 'down',
        'connection' => '',
        'ip'         => '',
        'method'     => '',
        'mac'        => ''
    ];
    // Get device state and active connection name
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
    // Get IPv4 address (first one if multiple)
    $ipLine = [];
    exec("nmcli -t -f IP4.ADDRESS dev show $if | grep IP4.ADDRESS", $ipLine);
    if (!empty($ipLine)) {
        $addr = explode(':', $ipLine[0]);
        if (isset($addr[1])) {
            $iface_status[$if]['ip'] = explode('/', $addr[1])[0];  // strip prefix length
        }
    }
    // Get IPv4 method (manual vs auto) if connected
    if ($iface_status[$if]['connection'] !== '') {
        $methLine = [];
        $conn_name_arg = escapeshellarg($iface_status[$if]['connection']);
        exec("nmcli -t -f ipv4.method connection show $conn_name_arg", $methLine);
        if (!empty($methLine)) {
            $methodVal = trim($methLine[0]);
            $iface_status[$if]['method'] = (strpos($methodVal, 'manual') !== false) ? 'Static' : 'DHCP';
        }
    }
    // Get MAC address via nmcli dev show (GENERAL.HWADDR)
    $macLine = [];
    exec("nmcli -t -f GENERAL.HWADDR dev show $if | grep GENERAL.HWADDR", $macLine);
    if (!empty($macLine)) {
        $macParts = explode(':', $macLine[0], 2);
        if (isset($macParts[1])) {
            $iface_status[$if]['mac'] = trim($macParts[1]);
        }
    }
}

// Output the HTML for the main interface
echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Network Manager</title>';
echo '<link href="/css/bootstrap.min.css" rel="stylesheet">';
echo '
<style>
    table th, table td {
        vertical-align: middle !important;
    }
    .form-check-inline {
        margin-right: 0.5rem;
    }
    .form-control-sm {
        padding-top: 0.25rem;
        padding-bottom: 0.25rem;
    }
</style>';
echo '<style> body { padding:20px; } </style>';  // minor padding
echo '</head><body>';
echo '<div class="container-fluid">';

// Header with title and logout button
echo '<div class="d-flex justify-content-between align-items-center mb-3">';
echo '<h3 class="m-0">Network Manager</h3>';
echo '<form method="get" class="m-0"><button type="submit" name="logout" class="btn btn-outline-secondary btn-sm">Logout</button></form>';
echo '</div>';

// Show any messages (success or error) at top
if ($message) {
    echo '<div class="alert alert-success">' . $message . '</div>';
}
if ($error) {
    echo '<div class="alert alert-danger">' . $error . '</div>';
}

// ** Current Network Status Section **
echo '<div class="card mb-4"><div class="card-header">Current Network Status</div>';
echo '<div class="card-body p-2">';
echo '<div class="table-responsive"><table class="table table-sm align-middle mb-0">';
echo '<thead><tr><th>Interface</th><th>Status</th><th>IP Address</th><th>MAC Address</th><th>Mode</th><th>Configure</th></tr></thead>';
echo '<tbody>';
foreach ($iface_status as $if => $st) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars(strtoupper($if)) . '</td>';
    // Status: if connected, show connected (and SSID for wifi), otherwise show state
    $stateText = ucfirst($st['state']);
    if ($st['connection']) {
        if ($if === 'wlan0') {
            $stateText = 'Connected to ' . htmlspecialchars($st['connection']);
        } else if ($st['state'] === 'connected') {
            $stateText = 'Connected';
        }
    } else if ($st['state'] === 'disconnected' || $st['state'] === 'unavailable') {
        $stateText = 'Disconnected';
    }
    echo '<td>' . htmlspecialchars($stateText) . '</td>';
    echo '<td>' . ($st['ip'] ? htmlspecialchars($st['ip']) : '-') . '</td>';
    echo '<td>' . ($st['mac'] ? htmlspecialchars($st['mac']) : '-') . '</td>';
    echo '<td>' . ($st['method'] ? htmlspecialchars($st['method']) : '-') . '</td>';
    // Configuration form (inline) for switching DHCP/Static


echo '<td>';
echo '<form method="post" class="d-flex align-items-center flex-nowrap gap-2">';
echo '<input type="hidden" name="iface" value="' . htmlspecialchars($if) . '">';

// DHCP/Static Radio
echo '<div class="form-check form-check-inline m-0">';
echo '<input class="form-check-input ip-mode-toggle" type="radio" name="ip_mode" value="dhcp" id="'.$if.'_dhcp" '.($isStatic ? '' : 'checked').'>';
echo '<label class="form-check-label" for="'.$if.'_dhcp">DHCP</label>';
echo '</div>';

echo '<div class="form-check form-check-inline m-0">';
echo '<input class="form-check-input ip-mode-toggle" type="radio" name="ip_mode" value="static" id="'.$if.'_static" '.($isStatic ? 'checked' : '').'>';
echo '<label class="form-check-label" for="'.$if.'_static">Static</label>';
echo '</div>';

// Static IP Fields (initially hidden if DHCP selected)
$staticClass = $isStatic ? '' : 'd-none';
echo '<div class="static-fields d-flex flex-nowrap align-items-center gap-2 ' . $staticClass . '">';
echo '<input type="text" class="form-control form-control-sm static-field" name="static_ip" placeholder="IP" style="max-width:120px;">';
echo '<input type="text" class="form-control form-control-sm static-field" name="static_mask" placeholder="Mask" style="max-width:100px;">';
echo '<input type="text" class="form-control form-control-sm static-field" name="static_gw" placeholder="Gateway" style="max-width:120px;">';
echo '<input type="text" class="form-control form-control-sm static-field" name="static_dns" placeholder="DNS" style="max-width:140px;">';
echo '</div>';

// Apply button
echo '<button type="submit" class="btn btn-sm btn-primary">Apply</button>';
echo '</form>';
echo '</td>';



    echo '</tr>';
}
echo '</tbody></table></div>';
echo '</div></div>';  // end card

// ** Wi-Fi Networks Section **
echo '<div class="card mb-4">';
echo '<div class="card-header d-flex justify-content-between align-items-center">';
echo 'Wi-Fi Networks';
echo '<form method="post" class="m-0"><button type="submit" name="scan_refresh" class="btn btn-sm btn-secondary">Refresh</button></form>';
echo '</div>';
echo '<div class="card-body p-0">';
echo '<div class="table-responsive"><table class="table table-hover table-sm mb-0">';
echo '<thead><tr><th>SSID</th><th>Signal</th><th>Security</th><th></th></tr></thead><tbody>';
if (empty($wifi_list)) {
    echo '<tr><td colspan="4" class="text-muted">No networks found.</td></tr>';
} else {

foreach ($wifi_list as $net) {
    $ssid_display = $net['ssid'] !== '' ? htmlspecialchars($net['ssid']) : '<em>(Hidden)</em>';
    $bssid_display = '<small class="text-muted" style="font-size: 80%; font-family: monospace;">' . htmlspecialchars($net['bssid']) . '</small>';

    $signalPercent = intval($net['signal']);
    $signalColor = 'bg-danger';
    if ($signalPercent >= 75) $signalColor = 'bg-success';
    elseif ($signalPercent >= 50) $signalColor = 'bg-warning';

    echo '<tr' . ($net['in_use'] ? ' class="table-success"' : '') . '>';

    // SSID + BSSID
    echo "<td>$ssid_display<br>$bssid_display</td>";

    // Signal bar
    echo '<td style="min-width:100px;">';
    echo '<div class="progress" style="height: 4px;">';
    echo '<div class="progress-bar ' . $signalColor . '" role="progressbar" style="width: ' . $signalPercent . '%;" aria-valuenow="' . $signalPercent . '" aria-valuemin="0" aria-valuemax="100"></div>';
    echo '</div>';
    echo '<small class="text-muted">' . $signalPercent . '%</small>';
    echo '</td>';

    // Security
    echo '<td>' . htmlspecialchars($net['security']) . '</td>';

    // Connect button or Connected indicator
    echo '<td>';
    if ($net['in_use']) {
        echo '<span class="text-success">Connected</span>';
    } else {
        echo '<form method="post" class="wifi-form d-flex align-items-center m-0" data-requires-password="' . 
             (stripos($net["security"], "WPA") !== false ? "true" : "false") . '">';
        echo '<input type="hidden" name="connect_ssid" value="'. htmlspecialchars($net['ssid']) .'">';
        echo '<div class="form-elements d-flex align-items-center">';
        echo '<button type="button" class="btn btn-sm btn-primary connect-btn">Connect</button>';
        echo '</div>';
        echo '</form>';
    }
    echo '</td></tr>';
}

}
echo '</tbody></table></div>';
echo '</div></div>';  // end card

// Simple JavaScript to toggle visibility of static IP fields when radio changes
echo '<script>
document.addEventListener("DOMContentLoaded", function () {
    // Toggle visibility of static IP fields
    document.querySelectorAll(".ip-mode-toggle").forEach(function (radio) {
        radio.addEventListener("change", function () {
            const form = this.closest("form");
            const showStatic = this.value === "static";
            const fields = form.querySelector(".static-fields");
            if (fields) {
                fields.classList.toggle("d-none", !showStatic);
            }
        });
    });
});
</script>';

echo '</div></body></html>';
?>
