#!/bin/bash

#raspi-config nonint do_wifi_country GB

SRC="/boot/firmware/cmdline.txt"
SERIAL_DEST="/boot/firmware/cmdline_serial.txt"
USB_DEST="/boot/firmware/cmdline_usb.txt"
cp "$SRC" "$SERIAL_DEST"
cp "$SRC" "$USB_DEST"
sed -i 's/console=serial0,115200 //g' "$USB_DEST"
sed -i 's/g_serial/g_ether/g' "$USB_DEST"
echo "Copied and modified $SRC"
