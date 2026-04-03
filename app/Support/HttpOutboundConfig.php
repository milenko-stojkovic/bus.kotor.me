<?php

namespace App\Support;

/**
 * Spaja root timeout-e u {@see config('http-outbound')} sa opcionim per-endpoint vrednostima.
 * Ključ endpointa (npr. deposit, create_session) može biti izostavljen ili imati samo delimične ključeve.
 */
final class HttpOutboundConfig
{
    /**
     * @param  'create_session'|'status_inquiry'  $endpoint
     * @return array{connect_timeout: float, timeout: float}
     */
    public static function bankart(string $endpoint): array
    {
        return self::merge('bankart', $endpoint);
    }

    /**
     * @param  'deposit'|'receipt'  $endpoint
     * @return array{connect_timeout: float, timeout: float, verify_ssl: bool}
     */
    public static function fiscal(string $endpoint): array
    {
        $out = self::merge('fiscal', $endpoint);
        $f = config('http-outbound.fiscal', []);
        $out['verify_ssl'] = (bool) ($f['verify_ssl'] ?? true);

        return $out;
    }

    private static function merge(string $service, string $endpoint): array
    {
        $c = config("http-outbound.{$service}", []);
        $child = $c[$endpoint] ?? null;
        $child = is_array($child) ? $child : [];

        $rootConnect = (float) ($c['connect_timeout'] ?? 5);
        $rootTimeout = (float) ($c['timeout'] ?? 25);

        $cc = $child['connect_timeout'] ?? null;
        $tt = $child['timeout'] ?? null;

        return [
            'connect_timeout' => self::floatOr($cc, $rootConnect),
            'timeout' => self::floatOr($tt, $rootTimeout),
        ];
    }

    private static function floatOr(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (float) $value;
    }
}
