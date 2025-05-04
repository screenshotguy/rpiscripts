#!/bin/bash
MODE=$1
CMDLINE_FILE="/boot/firmware/cmdline.txt"
GADGET_FILE="/root/files/cmdline_gadget.txt"
SERIAL_FILE="/root/files/cmdline_serial.txt"

if [ "$MODE" = "gadget" ]; then
    sudo cp $GADGET_FILE $CMDLINE_FILE
elif [ "$MODE" = "serial" ]; then
    sudo cp $SERIAL_FILE $CMDLINE_FILE
else
    echo "Usage: $0 {gadget|serial}"
    exit 1
fi

echo "Mode set to $MODE. Reboot to apply changes."
