#!/bin/bash
# Resize root filesystem
#raspi-config --expand-rootfs

# Set hostname
#echo "my-pi" > /etc/hostname
#sed -i "s/raspberrypi/my-pi/g" /etc/hosts

# Enable SSH
#systemctl enable ssh
echo F > /root/FIRSTBOOTSERVICE

# Clean up and disable firstboot
systemctl disable firstboot
rm /usr/local/bin/firstboot.sh
