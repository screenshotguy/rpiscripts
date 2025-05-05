#!/bin/bash
set -euo pipefail

# Check root privileges
[[ $EUID -eq 0 ]] || { echo "Run as root"; exit 1; }

# Log to tmpfs
LOG="/tmp/firstboot.log"
echo "Starting firstboot: $(date)" | tee -a "$LOG"

# Set hostname
HOSTNAME="pi-$RANDOM"
echo "$HOSTNAME" > /etc/hostname
sed -i "s/raspberrypi/$HOSTNAME/g" /etc/hosts
echo "Set hostname: $HOSTNAME" | tee -a "$LOG"

# Set timezone to UTC
ln -sf /usr/share/zoneinfo/UTC /etc/localtime
dpkg-reconfigure -f noninteractive tzdata
echo "Set timezone: UTC" | tee -a "$LOG"

# Enable SSH
systemctl enable ssh
echo "Enabled SSH" | tee -a "$LOG"

# Expand filesystem
raspi-config --expand-rootfs
echo "Expanded filesystem" | tee -a "$LOG"

# Regenerate SSH host keys
rm -f /etc/ssh/ssh_host_*
dpkg-reconfigure openssh-server
echo "Regenerated SSH keys" | tee -a "$LOG"

# Regenerate SSL certificates
SSL_DIR="/etc/ssl/local"
CRT="${SSL_DIR}/pi.crt"
KEY="${SSL_DIR}/pi.key"
install -d -m 700 "$SSL_DIR"
openssl req -x509 -nodes -days 3650 -newkey rsa:4096 \
    -keyout "$KEY" -out "$CRT" \
    -subj "/CN=$HOSTNAME.local" \
    -addext "subjectAltName=DNS:$HOSTNAME.local,IP:192.168.8.1"
chmod 600 "$KEY"
echo "Regenerated SSL certificates" | tee -a "$LOG"

# Enable OverlayFS and read-only boot
#raspi-config nonint do_overlayfs 0
#raspi-config nonint do_boot_ro 0
echo "Enabled OverlayFS and read-only boot" | tee -a "$LOG"

# Add tmpfs for Nginx
#FSTAB="/etc/fstab"
#echo "tmpfs /var/lib/nginx tmpfs defaults,noatime 0 0" >> "$FSTAB"
#echo "Added tmpfs for /var/lib/nginx" | tee -a "$LOG"

# Install toggle script
#install -m 755 /usr/local/bin/toggle-ro-rw.sh /usr/local/bin/toggle-ro-rw.sh
#echo "Installed toggle-ro-rw.sh" | tee -a "$LOG"

# Clean up
#systemctl disable firstboot
#rm /usr/local/bin/firstboot.sh
#echo "Disabled firstboot and removed script" | tee -a "$LOG"

echo "Firstboot complete: $(date). Rebooting to apply OverlayFS." | tee -a "$LOG"
#reboot
