<?php
/**
 * UI rendering functions
 */
function renderLoginPage($login_error) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Network Manager Login</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    table th, table td { vertical-align: middle !important; }
    .form-check-inline { margin-right: 0.5rem; }
    .form-control-sm { padding-top: 0.25rem; padding-bottom: 0.25rem; }
  </style>
</head>
<body class="bg-light d-flex align-items-center justify-content-center" style="height:100vh;">
  <div class="card shadow-sm p-4" style="min-width:300px;">
    <h4 class="mb-3">Login</h4>
    <?php if ($login_error): ?>
      <div class="alert alert-danger" role="alert">
        <?= htmlspecialchars($login_error) ?>
      </div>
    <?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label for="username" class="form-label">Username</label>
        <input type="text" class="form-control" id="username" name="login_username" required />
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <input type="password" class="form-control" id="password" name="login_password" required />
      </div>
      <button type="submit" class="btn btn-primary w-100">Login</button>
    </form>
  </div>
</body>
</html>
<?php
    return ob_get_clean();
}

function renderMainPage($message, $error, $wifi_list, $saved_connections, $iface_status) {
    ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Network Manager</title>
  <link href="/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    table th, table td { vertical-align: middle !important; }
    .form-check-inline { margin-right: 0.5rem; }
    .form-control-sm { padding-top: 0.25rem; padding-bottom: 0.25rem; }
    body { padding: 20px; }
    /* Wi-Fi Table Styling */
    .wifi-table th {
      background-color: #f8f9fa;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.85rem;
      color: #343a40;
      border-bottom: 2px solid #dee2e6;
    }
    .wifi-table td {
      font-size: 0.9rem;
      color: #495057;
    }
    .wifi-table tr:hover {
      background-color: #f1f3f5;
    }
    .wifi-table .ssid-cell {
      max-width: 200px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
    .wifi-table .bssid {
      font-size: 0.75rem;
      color: #6c757d;
    }
    .wifi-table .progress {
      height: 8px;
      border-radius: 4px;
      background-color: #e9ecef;
    }
    .wifi-table .progress-bar {
      transition: width 0.3s ease;
    }
    .wifi-table .signal-text {
      font-size: 0.8rem;
      color: #6c757d;
    }
    .wifi-table .action-cell form {
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .wifi-table .btn-connect {
      font-size: 0.85rem;
      padding: 0.25rem 0.75rem;
    }
    .wifi-table .connected-text {
      font-weight: 500;
      color: #28a745;
    }
    .wifi-table .form-control {
      font-size: 0.85rem;
      padding: 0.25rem 0.5rem;
    }
    .card-wifi {
      border: none;
      border-radius: 0.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    .card-wifi .card-header {
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      font-weight: 500;
      font-size: 1.1rem;
      color: #343a40;
    }
    .card-wifi .btn-refresh {
      font-size: 0.85rem;
      padding: 0.25rem 0.75rem;
      background-color: #6c757d;
      border-color: #6c757d;
    }
    .card-wifi .btn-refresh:hover {
      background-color: #5c636a;
      border-color: #5c636a;
    }
  </style>
</head>
<body>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="m-0">Network Manager</h3>
  <div>
    <a href="http://<?php echo $_SERVER['SERVER_ADDR']; ?>:4200" target="_blank" class="btn btn-outline-success btn-sm me-2">Terminal</a>
    <a href="/" class="btn btn-outline-primary btn-sm me-2">Home</a>
    <form method="get" class="d-inline">
      <button type="submit" name="logout" class="btn btn-outline-secondary btn-sm">Logout</button>
    </form>
  </div>
</div>

  <?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>

  <ul class="nav nav-tabs mb-3" id="managerTabs" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="wifi-tab" data-bs-toggle="tab" data-bs-target="#wifiTab" type="button" role="tab" aria-controls="wifiTab" aria-selected="true">Wi-Fi Networks</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="saved-tab" data-bs-toggle="tab" data-bs-target="#savedTab" type="button" role="tab" aria-controls="savedTab" aria-selected="false">Saved Connections</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="current-tab" data-bs-toggle="tab" data-bs-target="#currentTab" type="button" role="tab" aria-controls="currentTab" aria-selected="false">Current Network Status</button>
    </li>
  </ul>

  <div class="tab-content" id="managerTabsContent">
    <div class="tab-pane fade show active" id="wifiTab" role="tabpanel" aria-labelledby="wifi-tab">
      <div class="card card-wifi mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
          <span>Wi-Fi Networks</span>
          <form method="post" class="m-0">
            <button type="submit" name="scan_refresh" class="btn btn-refresh">Refresh</button>
          </form>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover table-sm wifi-table mb-0">
              <thead>
                <tr>
                  <th>SSID</th>
                  <th>Signal</th>
                  <th>Security</th>
                  <th style="width: 200px;">Action</th>
                </tr>
              </thead>
              <tbody>
              <?php if (empty($wifi_list)): ?>
                <tr><td colspan="4" class="text-muted">No networks found.</td></tr>
              <?php else: ?>
                <?php foreach ($wifi_list as $net): 
                  $ssid_display = $net['ssid'] !== '(hidden)' ? htmlspecialchars($net['ssid']) : '<em>(Hidden)</em>';
                  $bssid_display = '<div class="bssid">' . htmlspecialchars($net['bssid']) . '</div>';
                  $signalPercent = intval($net['signal']);
                  $signalColor = $signalPercent >= 75 ? 'bg-success' : ($signalPercent >= 50 ? 'bg-warning' : 'bg-danger');
                ?>
                  <tr <?= $net['in_use'] ? 'class="table-success"' : '' ?>>
                    <td class="ssid-cell">
                      <?= $ssid_display ?><br><?= $bssid_display ?>
                    </td>
                    <td style="min-width:120px;">
                      <div class="progress">
                        <div class="progress-bar <?= $signalColor ?>" role="progressbar" style="width: <?= $signalPercent ?>%;" aria-valuenow="<?= $signalPercent ?>" aria-valuemin="0" aria-valuemax="100"></div>
                      </div>
                      <div class="signal-text"><?= $signalPercent ?>%</div>
                    </td>
                    <td><?= htmlspecialchars($net['security']) ?></td>
                    <td class="action-cell">
                      <?php if ($net['in_use']): ?>
                        <span class="connected-text">Connected</span>
                      <?php else: ?>
                        <form method="get" action="wifi_connect.php" class="m-0">
                          <input type="hidden" name="ssid" value="<?= htmlspecialchars($net['ssid']) ?>">
                          <input type="hidden" name="security" value="<?= htmlspecialchars($net['security']) ?>">
                          <button type="submit" class="btn btn-sm btn-primary btn-connect">Connect</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="savedTab" role="tabpanel" aria-labelledby="saved-tab">
      <div class="card mb-4">
        <div class="card-header">Saved Connections</div>
        <div class="card-body p-0">
          <?php if (empty($saved_connections)): ?>
            <div class="p-3 text-muted">No saved connections found.</div>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm table-hover mb-0">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>UUID</th>
                    <th>Type</th>
                    <th>Device</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                <?php foreach ($saved_connections as $conn): ?>
                  <tr>
                    <td><?= htmlspecialchars($conn['name']) ?></td>
                    <td style="font-family: monospace;"><?= htmlspecialchars($conn['uuid']) ?></td>
                    <td><?= htmlspecialchars($conn['type']) ?></td>
                    <td><?= $conn['device'] ? htmlspecialchars($conn['device']) : '-' ?></td>
                    <td>
                      <div class="d-flex gap-1">
                        <form method="post" class="m-0">
                          <input type="hidden" name="activate_connection" value="<?= htmlspecialchars($conn['uuid']) ?>">
                          <button type="submit" class="btn btn-sm btn-primary">Activate</button>
                        </form>
                        <form method="post" class="m-0">
                          <input type="hidden" name="delete_connection" value="<?= htmlspecialchars($conn['uuid']) ?>">
                          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="tab-pane fade" id="currentTab" role="tabpanel" aria-labelledby="current-tab">
      <div class="card mb-4">
        <div class="card-header">Current Network Status</div>
        <div class="card-body p-2">
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead>
                <tr>
                  <th>Interface</th>
                  <th>Status</th>
                  <th>IP Address</th>
                  <th>MAC Address</th>
                  <th>Mode</th>
                  <th>Configure</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($iface_status as $if => $st): 
                  $isStatic = ($st['method'] === 'Static');
              ?>
                <tr>
                  <td><?= htmlspecialchars(strtoupper($if)) ?></td>
                  <td>
                    <?php
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
                      echo htmlspecialchars($stateText);
                    ?>
                  </td>
                  <td><?= $st['ip'] ? htmlspecialchars($st['ip']) : '-' ?></td>
                  <td><?= $st['mac'] ? htmlspecialchars($st['mac']) : '-' ?></td>
                  <td><?= $st['method'] ? htmlspecialchars($st['method']) : '-' ?></td>
                  <td>
                    <form method="post" class="d-flex align-items-center flex-nowrap gap-2">
                      <input type="hidden" name="iface" value="<?= htmlspecialchars($if) ?>">
                      <div class="form-check form-check-inline m-0">
                        <input class="form-check-input ip-mode-toggle" type="radio" name="ip_mode" value="dhcp" id="<?= $if ?>_dhcp" <?= $isStatic ? '' : 'checked' ?>>
                        <label class="form-check-label" for="<?= $if ?>_dhcp">DHCP</label>
                      </div>
                      <div class="form-check form-check-inline m-0">
                        <input class="form-check-input ip-mode-toggle" type="radio" name="ip_mode" value="static" id="<?= $if ?>_static" <?= $isStatic ? 'checked' : '' ?>>
                        <label class="form-check-label" for="<?= $if ?>_static">Static</label>
                      </div>
                      <div class="static-fields d-flex flex-nowrap align-items-center gap-2 <?= $isStatic ? '' : 'd-none' ?>">
                        <input type="text" class="form-control form-control-sm static-field" name="static_ip" placeholder="IP" style="max-width:120px;">
                        <input type="text" class="form-control form-control-sm static-field" name="static_mask" placeholder="Mask" style="max-width:100px;">
                        <input type="text" class="form-control form-control-sm static-field" name="static_gw" placeholder="Gateway" style="max-width:120px;">
                        <input type="text" class="form-control form-control-sm static-field" name="static_dns" placeholder="DNS" style="max-width:140px;">
                      </div>
                      <button type="submit" class="btn btn-sm btn-primary">Apply</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".ip-mode-toggle").forEach(function (radio) {
    radio.addEventListener("change", function () {
      const form = this.closest("form");
      const showStatic = (this.value === "static");
      const fields = form.querySelector(".static-fields");
      if (fields) {
        fields.classList.toggle("d-none", !showStatic);
      }
    });
  });
});
</script>
</body>
</html>
<?php
    return ob_get_clean();
}
?>
