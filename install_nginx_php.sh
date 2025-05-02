#!/usr/bin/env bash
# install_webstack.sh – ultra-lean Nginx + PHP-FPM (≈ 35 MB disk / <3 MB idle RAM)
# -------------------------------------------------------------------------------
#   • Installs nginx-light + php-fpm  (no recommends)
#   • Creates a tiny nginx.conf & single server block
#   • Switches PHP-FPM to ondemand, 3 children, 64 MB memory_limit
#   • Disables access log & unnecessary PHP extensions         (can re-enable!)
#   • Starts / restarts services and shows RAM footprint
# -------------------------------------------------------------------------------

set -euo pipefail

REQUIRED_CMDS=(apt sed tee awk systemctl)
for c in "${REQUIRED_CMDS[@]}"; do command -v "$c" >/dev/null || { echo "$c missing"; exit 1; }; done
[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)"; exit 1; }

echo "==> Updating package index …"
apt update -qq

echo "==> Installing nginx-light + php-fpm (no recommends) …"
apt install -y --no-install-recommends nginx-light php-fpm

# ────────────────────────── Detect PHP version ────────────────────────────
PHP_VER=$(ls /etc/php | sort -V | tail -n1)            # e.g. 8.2
PHP_POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
PHP_INI="/etc/php/${PHP_VER}/fpm/php.ini"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"

echo "==> PHP version detected: ${PHP_VER}"

# ───────────────────────── Nginx main config tweak ────────────────────────
echo "==> Writing trim nginx.conf …"
cp /etc/nginx/nginx.conf /etc/nginx/nginx.conf.bak.$(date +%s)

cat >/etc/nginx/nginx.conf <<'NG'
user www-data;
worker_processes 1;
pid /run/nginx.pid;

events { worker_connections 128; }

http {
    include       /etc/nginx/mime.types;
    default_type  application/octet-stream;

    access_log off;
    error_log /var/log/nginx/error.log crit;

    sendfile on;
    keepalive_timeout 15;

    gzip on;

    include /etc/nginx/conf.d/*.conf;
    include /etc/nginx/sites-enabled/*;
}
NG

# ─────────────────────── Minimal server block ─────────────────────────────
echo "==> Creating single site /var/www/html (PHP enabled) …"
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true

cat >/etc/nginx/sites-available/pi <<PI
server {
    listen 80 default_server;
    server_name _;

    root /var/www/html;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
    }
}
PI

ln -sf ../sites-available/pi /etc/nginx/sites-enabled/pi

# ─────────────────────── PHP-FPM memory tweaks ────────────────────────────
echo "==> Tuning PHP-FPM pool (${PHP_POOL}) …"
sed -i 's/^pm\s*=.*/pm = ondemand/'              "$PHP_POOL"
sed -i 's/^pm\.max_children\s*=.*/pm.max_children = 3/' "$PHP_POOL"
sed -i 's/^;*pm\.process_idle_timeout\s*=.*/pm.process_idle_timeout = 10s/' "$PHP_POOL"
sed -i 's/^;*pm\.max_requests\s*=.*/pm.max_requests = 200/' "$PHP_POOL"

echo "==> Setting memory_limit = 64M …"
sed -i 's/^memory_limit = .*/memory_limit = 64M/' "$PHP_INI"

echo "==> Disabling rarely-needed PHP extensions (xml*, opcache, pdo_mysql, mysqli) …"
phpdismod -v "$PHP_VER" -s fpm xmlreader xmlwriter xmlrpc opcache pdo_mysql mysqli >/dev/null || true

# ───────────────────────── Restart services ───────────────────────────────
echo "==> Reloading Nginx & PHP-FPM …"
systemctl restart php${PHP_VER}-fpm
systemctl restart nginx

# ───────────────────────── Test page --------------------------------------
echo "<?php phpinfo(); ?>" >/var/www/html/index.php

# copy web root
mv html/* /var/www/html/

# ───────────────────────── Report footprint --------------------------------
RSS=$(ps --no-headers -C nginx,php-fpm -o rss | awk '{sum+=$1} END{printf "%.1f", sum/1024}')
echo -e "\n✅  Installation complete."
echo "    • Visit http://<Pi-IP>/info.php to verify PHP."
echo "    • Current nginx+php-fpm RSS memory: ${RSS} MiB"

exit 0

