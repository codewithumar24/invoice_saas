<?php

namespace App\Services;

/** Time-based one-time passwords (RFC 6238), compatible with Google Authenticator, Authy, 1Password... */
class TwoFactorService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::ALPHABET[random_int(0, 31)];
        }

        return $secret;
    }

    public function otpauthUrl(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account).'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits=6&period=30';
    }

    /** Accept the current code and one step either side (clock drift). */
    public function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $counter = intdiv(time(), 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeAt($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public function codeAt(string $secret, int $counter): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0).pack('N*', $counter), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /** @return string[] one-time backup codes */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return array_map(fn () => strtolower(bin2hex(random_bytes(4)).'-'.bin2hex(random_bytes(4))), range(1, $count));
    }

    private function base32Decode(string $secret): string
    {
        $bits = '';

        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $position = strpos(self::ALPHABET, $char);

            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        $binary = '';

        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
