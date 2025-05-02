#!/usr/bin/env php
<?php
/**
 * blink_ip_once.php  –  Blink the Pi Zero 2 W ACT LED once with the current IPv4 address.
 *
 * • Requires root (sudo) because it writes to /sys/class/leds/ACT/.
 * • Encoding: digit 0 → 10 flashes, digits 1-9 → that many flashes.
 * • Header / footer: one long 1-second flash so you can spot the start/end.
 * • After finishing, the original /sys trigger is restored automatically.
 */

const LED_PATH   = '/sys/class/leds/ACT';
const BRIGHTNESS = LED_PATH . '/brightness';
const TRIGGER    = LED_PATH . '/trigger';

/* ───── timing (seconds) ───── */
const LONG_FLASH_ON  = 1.0;   // header / footer LED-on duration
const LONG_FLASH_OFF = 0.5;   // pause after header & before footer
const TICK_ON        = 0.25;  // LED-on per short flash
const TICK_OFF       = 0.25;  // LED-off between short flashes
const DIGIT_GAP      = 0.75;  // gap between digits
const OCTET_GAP      = 1.50;  // gap between octets

/* ───── low-level helpers ───── */
function led_on(): void  { file_put_contents(BRIGHTNESS, "1\n"); }
function led_off(): void { file_put_contents(BRIGHTNESS, "0\n"); }
function set_trigger(string $mode): void { file_put_contents(TRIGGER, $mode . "\n"); }
function sleep_f(float $sec): void { usleep((int)($sec * 1_000_000)); }

/* ───── flashing helpers ───── */
function long_flash(): void {
    led_on();  sleep_f(LONG_FLASH_ON);
    led_off(); sleep_f(LONG_FLASH_OFF);
}

function blink_ticks(int $n): void {
    $n = ($n === 0) ? 10 : $n;                 // encode 0 as 10 flashes
    for ($i = 0; $i < $n; $i++) {
        led_on();  sleep_f(TICK_ON);
        led_off(); sleep_f(TICK_OFF);
    }
}

function blink_digit(string $d): void {
    blink_ticks((int)$d);
    sleep_f(DIGIT_GAP);
}

function blink_octet(string $octet): void {
    foreach (str_split($octet) as $d) blink_digit($d);
    sleep_f(OCTET_GAP);
}

/* ───── discover an IPv4 address ───── */
function first_ipv4(): string {
    $raw = trim(shell_exec('hostname -I') ?? '');
    foreach (preg_split('/\s+/', $raw) as $ip) {
        if (str_contains($ip, '.') && !preg_match('/^(127|169\.254)\./', $ip)) {
            return $ip;
        }
    }
    fwrite(STDERR, "No suitable IPv4 address found.\n");
    exit(1);
}

/* ───── main routine ───── */
$ip       = first_ipv4();
$octets   = explode('.', $ip);
$original = trim(file_get_contents(TRIGGER));

echo "Flashing IP $ip once, then restoring LED …\n";

try {
    set_trigger('none');   // claim LED
    led_off();

    long_flash();                  // header
    foreach ($octets as $octet) {  // blink IP
        blink_octet($octet);
    }
    long_flash();                  // footer

} finally {
    // Give LED back to kernel (default to mmc0 if original was blank)
    set_trigger($original !== '' ? $original : 'mmc0');
    led_off();
}

