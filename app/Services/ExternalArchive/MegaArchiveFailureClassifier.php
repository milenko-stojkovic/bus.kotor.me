<?php

namespace App\Services\ExternalArchive;

/**
 * Conservative classification of MEGA client / process errors for v1 retry policy.
 * When uncertain, errors are non-transient.
 */
final class MegaArchiveFailureClassifier
{
    /**
     * @var list<string>
     */
    private const TRANSIENT_SUBSTRINGS = [
        'timeout',
        'timed out',
        'etimedout',
        'econnreset',
        'enotfound',
        'eai_again',
        'econnrefused',
        'econnaborted',
        'enetunreach',
        'epipe',
        'socket hang up',
        'socket hangup',
        'temporary failure',
        'temporarily unavailable',
        'service unavailable',
        'bad gateway',
        'gateway timeout',
        ' 502',
        ' 503',
        ' 504',
        '503',
        '502',
        '504',
        'rate limit',
        'too many requests',
    ];

    /**
     * Non-transient hints checked before transient (stricter).
     *
     * @var list<string>
     */
    private const NON_TRANSIENT_SUBSTRINGS = [
        'wrong password',
        'invalid password',
        'bad credentials',
        'authentication',
        'login failed',
        'login fail',
        'unable to log in',
        'folder not found',
        'folder missing',
        'no such folder',
        'unknown folder',
        'base folder',
        'not configured',
        'mega_email',
        'mega_password',
        'mega-archive.js missing',
        'empty mega script output',
        'invalid json from mega script',
        'local file does not exist',
        'does not exist on private disk',
        'corrupt',
        'invalid image',
        'archive is not in uploaded state',
        'enoent',
    ];

    public function isTransient(string $errorMessage): bool
    {
        $norm = mb_strtolower(trim($errorMessage));
        if ($norm === '') {
            return false;
        }

        foreach (self::NON_TRANSIENT_SUBSTRINGS as $needle) {
            if (str_contains($norm, $needle)) {
                return false;
            }
        }

        foreach (self::TRANSIENT_SUBSTRINGS as $needle) {
            if (str_contains($norm, $needle)) {
                return true;
            }
        }

        if (str_contains($norm, 'mega process failed')) {
            return $this->processFailureLooksTransient($norm);
        }

        return false;
    }

    public function shortReason(string $errorMessage): string
    {
        $t = trim($errorMessage);
        if ($t === '') {
            return 'unknown_error';
        }

        $oneLine = preg_replace('/\s+/', ' ', $t) ?? $t;

        return mb_substr($oneLine, 0, 200);
    }

    private function processFailureLooksTransient(string $norm): bool
    {
        foreach (self::TRANSIENT_SUBSTRINGS as $needle) {
            if (str_contains($norm, $needle)) {
                return true;
            }
        }

        return false;
    }
}
