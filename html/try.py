#!/usr/bin/env python3
"""
Blink the ACT LED *once* with the current IPv4 address, then restore the
original LED trigger.  Needs sudo.
"""

import subprocess, time, re
from pathlib import Path
from contextlib import contextmanager

LED      = Path("/sys/class/leds/ACT")
BRIGHT   = LED / "brightness"
TRIGGER  = LED / "trigger"

# ───────── timing constants ─────────
LONG_ON, LONG_OFF = 1.0, 0.5
TICK_ON, TICK_OFF = 0.25, 0.25
DIGIT_GAP, OCTET_GAP = 0.75, 1.50

# ───────── low-level helpers ─────────
led_on  = lambda: BRIGHT.write_text("1")
led_off = lambda: BRIGHT.write_text("0")
write_trigger = lambda t: TRIGGER.write_text(t)

def current_trigger() -> str:
    """Return the word enclosed in [...] in /sys/.../trigger."""
    txt = TRIGGER.read_text()
    m = re.search(r"\[(\w+)\]", txt)
    return m.group(1) if m else "mmc0"     # sensible fallback

@contextmanager
def led_claimed():
    orig = current_trigger()
    try:
        write_trigger("none")
        led_off()
        yield
    finally:
        write_trigger(orig)
        led_off()

# ───────── blink helpers ─────────
def ticks(n:int):
    n = 10 if n == 0 else n
    for _ in range(n):
        led_on();  time.sleep(TICK_ON)
        led_off(); time.sleep(TICK_OFF)

def digit(d:str):
    ticks(int(d)); time.sleep(DIGIT_GAP)

def octet(o:str):
    for ch in o: digit(ch)
    time.sleep(OCTET_GAP)

def long_flash():
    led_on();  time.sleep(LONG_ON)
    led_off(); time.sleep(LONG_OFF)

# ───────── grab IP & run once ─────────
def first_ipv4() -> str:
    out = subprocess.check_output(["hostname","-I"], text=True).strip()
    for ip in out.split():
        if "." in ip and not ip.startswith(("127.","169.254.")):
            return ip
    raise RuntimeError("no IPv4 address")

def main():
    ip = first_ipv4()
    print("Flashing", ip)
    with led_claimed():
        long_flash()                 # header
        for o in ip.split("."): octet(o)
        long_flash()                 # footer

if __name__ == "__main__":
    try:   main()
    except KeyboardInterrupt:
        write_trigger(current_trigger()); led_off()
        print("\nInterrupted; trigger restored.")

