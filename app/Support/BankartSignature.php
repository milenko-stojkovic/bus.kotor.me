<?php

namespace App\Support;

/**
 * HMAC-SHA512 potpis za Bankart API v3 (isti algoritam za POST i GET).
 *
 * @see https://gateway.bankart.si/documentation/apiv3
 */
final class BankartSignature
{
    public static function resolveSignaturePath(string $apiBase, string $path): string
    {
        $basePath = parse_url($apiBase, PHP_URL_PATH);
        $basePath = is_string($basePath) ? rtrim($basePath, '/') : '';

        return ($basePath !== '' ? $basePath : '').$path;
    }

    public static function sign(
        string $method,
        string $rawBody,
        string $contentType,
        string $date,
        string $requestUriPath,
        string $sharedSecret,
    ): ?string {
        if ($sharedSecret === '') {
            return null;
        }

        $method = strtoupper($method);
        $bodyHash = hash('sha512', $rawBody);
        $message = implode("\n", [
            $method,
            $bodyHash,
            $contentType,
            $date,
            $requestUriPath,
        ]);

        return base64_encode(hash_hmac('sha512', $message, $sharedSecret, true));
    }
}
