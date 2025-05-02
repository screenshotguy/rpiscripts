#!/usr/bin/env bash

set -e

echo "=== 1) Ensuring dtoverlay=dwc2 is set in /boot/firmware/config.txt ==="
if grep -q "dtoverlay=dwc2" /boot/firmware/config.txt; then
    echo "dtoverlay=dwc2 already present in /boot/firmware/config.txt"
else
    echo "dtoverlay=dwc2" | sudo tee -a /boot/firmware/config.txt
    echo "Added dtoverlay=dwc2 to /boot/firmware/config.txt"
fi

echo "=== 2) Backing up and modifying /boot/firmware/cmdline.txt ==="
# Make a backup
sudo cp /boot/firmware/cmdline.txt /boot/firmware/cmdline.txt.bak

# Read the existing cmdline into a variable
current_line=$(cat /boot/firmware/cmdline.txt)

# Remove references to GPIO serial console, e.g. console=serial0,115200
current_line=$(echo "$current_line" | sed 's/console=serial0,[0-9]*//g')

# Remove any existing references to modules-load= that mention dwc2,g_serial
# (in case we tried partial setup before)
current_line=$(echo "$current_line" | sed 's/modules-load=[^ ]*//g')

# Remove any existing console=ttyGS0 references
current_line=$(echo "$current_line" | sed 's/console=ttyGS0,[0-9]*//g')

# Insert modules-load=dwc2,g_serial console=ttyGS0,115200 right after 'rootwait'
# This is a simplistic approach assuming 'rootwait' is definitely in your cmdline
updated_line=$(echo "$current_line" \
    | sed 's/\(rootwait\)/\1 modules-load=dwc2,g_serial console=ttyGS0,115200/g')

# Clean up extra spaces
updated_line=$(echo "$updated_line" | tr -s ' ' | sed 's/^ *//;s/ *$//')

# Write it back
echo "$updated_line" | sudo tee /boot/firmware/cmdline.txt > /dev/null

echo "=== 3) Enabling serial-getty@ttyGS0.service ==="
sudo systemctl enable serial-getty@ttyGS0.service

echo "=== All done! Please reboot to apply changes. ==="
