#!/usr/bin/env bash
# ------------------------------------------------------------------
# Force Pi-OS to use a UTF-8 locale everywhere (non-interactive)
# ------------------------------------------------------------------
set -euo pipefail

TARGET_LOCALE="en_US.UTF-8"

echo "=== 1) Make sure the locale is generated ==="
if ! grep -q "^$TARGET_LOCALE UTF-8" /etc/locale.gen; then
  echo "$TARGET_LOCALE UTF-8" | sudo tee -a /etc/locale.gen
  LOCALE_ADDED=true
else
  LOCALE_ADDED=false
fi

if $LOCALE_ADDED; then
  sudo locale-gen
else
  echo "Locale already in /etc/locale.gen – skipping locale-gen"
fi

echo "=== 2) Set system-wide defaults ==="
# update-locale creates /etc/default/locale and keeps existing keys intact
sudo update-locale LANG="$TARGET_LOCALE" \
                   LANGUAGE="$TARGET_LOCALE" \
                   LC_ALL="$TARGET_LOCALE"

echo "=== 3) Ensure systemd’s environment is refreshed ==="
# Systemd picks variables from /etc/locale.conf if it exists (Fedora-style)
# and from /etc/default/locale on Debian/RPiOS.  We’ll create the Fedora
# file too for completeness; no harm if it already exists.
sudo bash -c "echo 'LANG=$TARGET_LOCALE' > /etc/locale.conf"

echo "=== 4) Make sure future non-login shells inherit LANG ==="
if ! grep -q 'export LANG=' /etc/profile.d/00-lang.sh 2>/dev/null; then
  echo "export LANG=$TARGET_LOCALE" | sudo tee /etc/profile.d/00-lang.sh
  sudo chmod 644 /etc/profile.d/00-lang.sh
fi

echo "=== 5) (Optional) Tell PHP-FPM & CGI to use UTF-8 ==="
# Only touch if php-fpm is installed
if systemctl list-unit-files | grep -q '^php.*fpm\.service'; then
  PHP_INI=$(php -r 'echo php_ini_loaded_file();' 2>/dev/null || true)
  if [ -n "$PHP_INI" ] && ! grep -q '^default_charset *= *"utf-8"' "$PHP_INI"; then
    sudo sed -i 's|^;*default_charset *=.*|default_charset = "utf-8"|' "$PHP_INI"
    sudo systemctl restart "$(systemctl --no-legend --type=service | awk '/php.*fpm/ {print $1}')" || true
    echo ">> default_charset set to UTF-8 in $PHP_INI"
  fi
fi

echo "=== 6) (Optional) Add Nginx charset header ==="
NGX_CONF="/etc/nginx/conf.d/charset.conf"
if [ ! -f "$NGX_CONF" ]; then
  sudo tee "$NGX_CONF" >/dev/null <<'EOF'
charset utf-8;
charset_types text/html text/plain text/css application/json application/javascript;
EOF
  sudo nginx -t && sudo systemctl reload nginx
fi

echo "=== 7) Done – reboot recommended so *every* process inherits the new locale ==="

