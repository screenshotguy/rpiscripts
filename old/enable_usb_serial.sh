#!/usr/bin/env bash
#
# usb_serial_setup.sh – enable USB-gadget serial (ttyGS0) on RPi Zero W 2
#
#   • Puts     dtoverlay=dwc2,dr_mode=peripheral    inside [all]
#   • Deletes  dtoverlay=dwc2,dr_mode=host          if present anywhere
#   • Adds     modules-load=dwc2,g_serial console=ttyGS0,115200
#       to /boot/firmware/cmdline.txt (after rootwait, single line kept)
#   • Enables  serial-getty@ttyGS0.service
#
# Safe-guards:
#   – automatic sudo re-exec if not root
#   – timestamped .bak copies of both files
#   – idempotent: you can run it again without duplicates
#
set -euo pipefail

##############################
# run as root (or sudo)
##############################
if [[ $EUID -ne 0 ]]; then
  exec sudo -- "$0" "$@"
fi

CONFIG=/boot/firmware/config.txt
CMDLINE=/boot/firmware/cmdline.txt
ts="$(date +%Y%m%d%H%M%S)"

echo "=== Backing up config.txt and cmdline.txt ==="
cp "$CONFIG"  "${CONFIG}.bak.${ts}"
cp "$CMDLINE" "${CMDLINE}.bak.${ts}"

########################################
# 1. CONFIG.TXT  –  keep gadget, remove host
########################################
echo "=== Cleaning host-only dwc2 lines in config.txt ==="
# delete any line that explicitly forces host mode
sed -i '/^dtoverlay=dwc2,.*dr_mode=host$/d' "$CONFIG"

# ensure an [all] section exists
if ! grep -q '^\[all\]' "$CONFIG"; then
  echo -e "\n[all]" >> "$CONFIG"
fi

# does [all] already contain a peripheral dwc2 line?
has_gadget=$(
  awk '
    /^\[/   { sect=$0; next }
    /dtoverlay=dwc2/ && sect=="[all]" && $0 !~ /dr_mode=host/ { found=1 }
    END { print (found ? "yes" : "no") }
  ' "$CONFIG"
)

if [[ $has_gadget == "no" ]]; then
  echo "Adding peripheral-mode overlay to [all]"
  sed -i '/^\[all\]/a dtoverlay=dwc2,dr_mode=peripheral' "$CONFIG"
else
  echo "Peripheral overlay already present in [all]"
fi

########################################
# 2. CMDLINE.TXT  –  insert gadget parameters
########################################
echo "=== Updating cmdline.txt ==="
line="$(cat "$CMDLINE")"

# drop any existing serial0 console, duplicate modules-load, or old ttyGS0
line="$(sed -E '
  s#console=serial0,[0-9]+ ?##g;
  s#modules-load=[^ ]* ?##g;
  s#console=ttyGS0,[0-9]+ ?##g
' <<<"$line")"

# insert after rootwait if not already present
if [[ $line != *"modules-load=dwc2,g_serial"* ]]; then
  line="$(sed 's/\brootwait\b/& modules-load=dwc2,g_serial console=ttyGS0,115200/' <<<"$line")"
fi

# squeeze whitespace
line="$(xargs <<<"$line")"

echo "$line" > "$CMDLINE"

########################################
# 3. Enable systemd getty on GS0
########################################
echo "=== Enabling serial-getty@ttyGS0.service ==="
systemctl enable serial-getty@ttyGS0.service

echo "=== All done – reboot to activate USB serial gadget! ==="
