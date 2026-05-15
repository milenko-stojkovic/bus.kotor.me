<?php

namespace App\Services\ExternalArchive;

use Illuminate\Support\Facades\Process;
use Throwable;

final class MegaDiagnoseService
{
    /**
     * Run MEGA diagnose via scripts/mega-archive.js diagnose.
     *
     * @return array{
     *     ok: bool,
     *     email_present: bool,
     *     password_present: bool,
     *     base_folder: string,
     *     node_version: string|null,
     *     login_ok: bool,
     *     folder_found: bool,
     *     root_children_sample: list<string>,
     *     error: string,
     *     node_binary: string,
     *     user_agent: string
     * }
     */
    public function run(): array
    {
        $email = trim((string) config('services.mega.email', ''));
        $password = (string) config('services.mega.password', '');
        $baseFolder = (string) config('services.mega.base_folder', 'bus.kotor');
        $nodeBinary = trim((string) config('services.mega.node_binary', ''));
        $binary = $nodeBinary !== '' ? $nodeBinary : 'node';
        $userAgent = $this->resolveMegaUserAgent();

        $empty = [
            'ok' => false,
            'email_present' => $email !== '',
            'password_present' => $password !== '',
            'base_folder' => $baseFolder,
            'node_version' => null,
            'login_ok' => false,
            'folder_found' => false,
            'root_children_sample' => [],
            'error' => 'MEGA_EMAIL / MEGA_PASSWORD not configured in Laravel config (.env → services.mega).',
            'node_binary' => $binary,
            'user_agent' => $userAgent,
        ];

        if ($email === '' || $password === '') {
            return $empty;
        }

        $script = base_path('scripts/mega-archive.js');
        if (! is_file($script)) {
            return [
                'ok' => false,
                'email_present' => true,
                'password_present' => true,
                'base_folder' => $baseFolder,
                'node_version' => null,
                'login_ok' => false,
                'folder_found' => false,
                'root_children_sample' => [],
                'error' => 'mega-archive.js missing.',
                'node_binary' => $binary,
                'user_agent' => $userAgent,
            ];
        }

        try {
            $result = Process::path(base_path())
                ->timeout(120)
                ->env([
                    'MEGA_EMAIL' => $email,
                    'MEGA_PASSWORD' => $password,
                    'MEGA_BASE_FOLDER' => $baseFolder,
                    'MEGA_USER_AGENT' => $userAgent,
                ])
                ->input('{}')
                ->run([$binary, $script, 'diagnose']);
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'email_present' => true,
                'password_present' => true,
                'base_folder' => $baseFolder,
                'node_version' => null,
                'login_ok' => false,
                'folder_found' => false,
                'root_children_sample' => [],
                'error' => 'MEGA diagnose process exception: '.$e->getMessage(),
                'node_binary' => $binary,
                'user_agent' => $userAgent,
            ];
        }

        $stdout = trim($result->output());
        $stderr = trim($result->errorOutput());

        $parsed = $this->parseDiagnoseStdout($stdout);
        if ($parsed !== null) {
            $parsed['node_binary'] = $binary;
            if (trim((string) ($parsed['user_agent'] ?? '')) === '') {
                $parsed['user_agent'] = $userAgent;
            }

            return $parsed;
        }

        $hint = $stderr !== '' ? $stderr : $stdout;

        return [
            'ok' => false,
            'email_present' => true,
            'password_present' => true,
            'base_folder' => $baseFolder,
            'node_version' => null,
            'login_ok' => false,
            'folder_found' => false,
            'root_children_sample' => [],
            'error' => 'MEGA diagnose: invalid or empty JSON from script. '.mb_substr($hint, 0, 400),
            'node_binary' => $binary,
            'user_agent' => $userAgent,
        ];
    }

    private function resolveMegaUserAgent(): string
    {
        $ua = trim((string) config('services.mega.user_agent', 'BusKotorArchive/1.0'));

        return $ua !== '' ? $ua : 'BusKotorArchive/1.0';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseDiagnoseStdout(string $stdout): ?array
    {
        if ($stdout === '' || ! str_starts_with($stdout, '{')) {
            return null;
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($decoded)) {
            return null;
        }

        if (! array_key_exists('ok', $decoded) || ! array_key_exists('login_ok', $decoded)) {
            return null;
        }

        return [
            'ok' => (bool) $decoded['ok'],
            'email_present' => (bool) ($decoded['email_present'] ?? false),
            'password_present' => (bool) ($decoded['password_present'] ?? false),
            'base_folder' => isset($decoded['base_folder']) ? (string) $decoded['base_folder'] : '',
            'node_version' => isset($decoded['node_version']) ? (string) $decoded['node_version'] : null,
            'login_ok' => (bool) $decoded['login_ok'],
            'folder_found' => (bool) ($decoded['folder_found'] ?? false),
            'root_children_sample' => $this->normalizeStringList($decoded['root_children_sample'] ?? []),
            'error' => isset($decoded['error']) ? (string) $decoded['error'] : '',
            'node_binary' => '',
            'user_agent' => isset($decoded['user_agent']) ? (string) $decoded['user_agent'] : '',
        ];
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            $out[] = (string) $item;
        }

        return $out;
    }
}
