#!/bin/bash

# Check if parameter is provided
if [ -z "$1" ]; then
  echo "Usage: $0 {usb|serial}"
  exit 1
fi

# Source and destination paths
SRC="/boot/firmware/cmdline.txt"
SERIAL_DEST="/boot/firmware/cmdline_serial.txt"
USB_DEST="/boot/firmware/cmdline_usb.txt"

case "$1" in
  serial)
    # Copy to cmdline_serial.txt
    cp "$SRC" "$SERIAL_DEST"
    echo "Copied $SRC to $SERIAL_DEST"
    ;;
  usb)
    # Copy to cmdline_usb.txt
    cp "$SRC" "$USB_DEST"
    # Modify cmdline_usb.txt: remove console=serial0,115200 and change g_serial to g_ether
    sed -i 's/console=serial0,115200 //g' "$USB_DEST"
    sed -i 's/g_serial/g_ether/g' "$USB_DEST"
    echo "Copied and modified $SRC to $USB_DEST"
    ;;
  *)
    echo "Invalid parameter. Use 'usb' or 'serial'"
    exit 1
    ;;
esac

