#!/usr/bin/env bash
# install_nginx_php_ssl.sh – ultra-lean Nginx + PHP-FPM + HTTPS
# -------------------------------------------------------------------------------
#   • Installs nginx-light + php-fpm (no recommends) + openssl
#   • Creates a self-signed RSA cert in /etc/ssl/local
#   • Builds minimal nginx.conf and single HTTP→HTTPS server block
#   • Tunes PHP-FPM for low-memory operation (ondemand, 3 children, 64 MB)
#   • Disables access logs & unnecessary PHP extensions
#   • Shows combined nginx+php-fpm RAM footprint on completion
# -------------------------------------------------------------------------------

set -euo pipefail

REQUIRED_CMDS=(apt sed tee awk systemctl openssl)
for c in "${REQUIRED_CMDS[@]}"; do command -v "$c" >/dev/null || { echo "$c missing"; exit 1; }; done
[[ $EUID -eq 0 ]] || { echo "Run as root (sudo)"; exit 1; }

echo "==> Updating package index …"
apt update -qq

echo "==> Installing nginx-light + php-fpm + openssl (no recommends) …"
apt install -y --no-install-recommends nginx-light php-fpm php8.2-mbstring openssl ca-certificates

# ────────────────────────── Detect PHP version ────────────────────────────
PHP_VER=$(ls /etc/php | sort -V | tail -n1)           # e.g. 8.2
PHP_POOL="/etc/php/${PHP_VER}/fpm/pool.d/www.conf"
PHP_INI="/etc/php/${PHP_VER}/fpm/php.ini"
PHP_SOCK="/run/php/php${PHP_VER}-fpm.sock"

echo "==> PHP version detected: ${PHP_VER}"

# ────────────────────── Generate self-signed certificate ──────────────────
SSL_DIR="/etc/ssl/local"
CRT="${SSL_DIR}/pi.crt"
KEY="${SSL_DIR}/pi.key"

if [[ ! -f "$CRT" ]]; then
    echo "==> Generating self-signed certificate …"
    install -d -m 700 "$SSL_DIR"
    openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
        -keyout "$KEY" -out "$CRT" \
        -subj "/CN=$(hostname -f)" \
        -addext "subjectAltName=DNS:$(hostname -f),IP:$(hostname -I | awk '{print $1}')"
    chmod 600 "$KEY"
fi

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

# ─────────────────────── Minimal HTTPS server block ───────────────────────
echo "==> Creating single HTTPS site /var/www/html …"
rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
SERVER_NAME="_"           # change to your FQDN if you have one

cat >/etc/nginx/sites-available/pi <<PI
# HTTP → HTTPS redirect
server {
    listen 80 default_server;
    server_name ${SERVER_NAME};
    return 301 https://\$host\$request_uri;
}

# HTTPS vhost
server {
    listen 443 ssl http2 default_server;
    server_name ${SERVER_NAME};

    # Self-signed cert (swap out if you later install Let's Encrypt)
    ssl_certificate     ${CRT};
    ssl_certificate_key ${KEY};
    ssl_session_cache   shared:SSL:1m;
    ssl_session_timeout 10m;

    # Modern, broadly compatible ciphers
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;

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
sed -i 's/^pm\s*=.*/pm = ondemand/'                     "$PHP_POOL"
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
echo "<?php phpinfo(); ?>" >/var/www/html/info.php

# copy web root (adjust path if needed)
if [[ -d html ]]; then
    cp -rf html/* /var/www/html/ || true
fi

# ───────────────────────── Report footprint -------------------------------
RSS=$(ps --no-headers -C nginx,php-fpm -o rss | awk '{sum+=$1} END{printf "%.1f", sum/1024}')
IP=$(hostname -I | awk '{print $1}')

echo -e "\n✅  Installation complete."
echo "    • Visit https://${IP}  (accept the browser warning for self-signed cert)."
echo "    • Visit https://${IP}/info.php to verify PHP."
echo "    • Current nginx+php-fpm RSS memory: ${RSS} MiB"

exit 0

