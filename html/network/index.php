<?php
/**
 * Main entry point for NetworkManager Web UI
 * Orchestrates authentication, data collection, and UI rendering
 */

mb_internal_encoding('UTF-8');
session_start();

// Include configuration and modules
require_once 'config.php';
require_once 'auth.php';
require_once 'network.php';
require_once 'data.php';
require_once 'ui.php';

// Handle authentication
$auth = new Auth($config['username'], $config['password']);
if (!$auth->isLoggedIn()) {
    echo renderLoginPage($auth->getLoginError());
    exit;
}

// Handle network actions
$network = new Network();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_connection'])) {
        $result = $network->deleteConnection($_POST['delete_connection']);
        $message = $result['message'];
        $error = $result['error'];
    } elseif (isset($_POST['activate_connection'])) {
        $result = $network->activateConnection($_POST['activate_connection']);
        $message = $result['message'];
        $error = $result['error'];
    } elseif (isset($_POST['connect_ssid'])) {
        $result = $network->connectWifi($_POST['connect_ssid'], $_POST['wifi_password'] ?? '');
        $message = $result['message'];
        $error = $result['error'];
    } elseif (isset($_POST['scan_refresh'])) {
        $network->rescanWifi();
    } elseif (isset($_POST['iface']) && isset($_POST['ip_mode'])) {
        $result = $network->configureInterface($_POST['iface'], $_POST['ip_mode'], $_POST);
        $message = $result['message'];
        $error = $result['error'];
    }
}

// Collect data
$data = new Data();
$wifi_list = $data->getWifiList();
$saved_connections = $data->getSavedConnections();
$iface_status = $data->getInterfaceStatus(['wlan0', 'eth0', 'usb0']);

// Render main UI
echo renderMainPage($message, $error, $wifi_list, $saved_connections, $iface_status);
?>
