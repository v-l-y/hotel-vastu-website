<?php

namespace App\Support;

use InvalidArgumentException;

final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function provisioningUri(
        string $account,
        string $secret,
        string $issuer = 'Hotel Vastu Premium'
    ): string {
        $label = rawurlencode($issuer.':'.$account);

        return 'otpauth://totp/'.$label
            .'?secret='.rawurlencode($secret)
            .'&issuer='.rawurlencode($issuer)
            .'&algorithm=SHA1&digits=6&period=30';
    }

    public function verify(
        string $secret,
        string $code,
        ?int $timestamp = null,
        int $window = 1
    ): bool {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv($timestamp ?? time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeForCounter($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function currentCode(string $secret, ?int $timestamp = null): string
    {
        return $this->codeForCounter(
            $secret,
            intdiv($timestamp ?? time(), 30)
        );
    }

    private function codeForCounter(string $secret, int $counter): string
    {
        if ($counter < 0) {
            throw new InvalidArgumentException('TOTP counter cannot be negative.');
        }

        $key = $this->base32Decode($secret);
        $binaryCounter = pack(
            'N2',
            ($counter >> 32) & 0xffffffff,
            $counter & 0xffffffff
        );
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    private function base32Encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';

        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    private function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');

        if ($encoded === '') {
            throw new InvalidArgumentException('TOTP secret is empty.');
        }

        $bits = '';

        foreach (str_split($encoded) as $character) {
            $position = strpos(self::ALPHABET, $character);

            if ($position === false) {
                throw new InvalidArgumentException('Invalid base32 TOTP secret.');
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';

        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) !== 8) {
                break;
            }

            $decoded .= chr(bindec($chunk));
        }

        return $decoded;
    }
}
