<?php

namespace App\Support;

/** Blocks server-side requests to internal addresses (SSRF protection for user-supplied webhook URLs). */
class SafeUrl
{
    public static function isPublic(string $url): bool
    {
        $parts = parse_url($url);

        if (! $parts || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) {
            return false;
        }

        $host = trim($parts['host'], '[]');

        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return false;
        }

        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : gethostbyname($host);

        // Unresolvable host names are left to fail at request time
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }
}
