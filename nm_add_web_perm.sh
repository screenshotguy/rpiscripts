cat << EOF | sudo tee /etc/polkit-1/localauthority/50-local.d/10-nmcli-webui.pkla
[Allow www-data to manage Wi-Fi from the web UI]
Identity=unix-user:www-data
Action=org.freedesktop.NetworkManager.*
ResultAny=yes
ResultInactive=yes
ResultActive=yes
EOF
[Allow www-data to manage Wi-Fi from the web UI]
Identity=unix-user:www-data
Action=org.freedesktop.NetworkManager.*
ResultAny=yes
ResultInactive=yes
ResultActive=yes

