<?php

namespace App\Services\ExternalArchive;

use App\Contracts\MegaArchiveClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Throwable;

final class MegaArchiveService implements MegaArchiveClient
{
    public function uploadLocalFile(string $absoluteLocalPath, string $generatedFileName): MegaUploadResult
    {
        return $this->uploadLocalFileToRelativePath($absoluteLocalPath, '', $generatedFileName);
    }

    public function uploadLocalFileToRelativePath(
        string $absoluteLocalPath,
        string $relativeDirectory,
        string $targetFileName,
    ): MegaUploadResult {
        return $this->runNode('upload', [
            'localPath' => $absoluteLocalPath,
            'targetName' => $targetFileName,
            'targetRelativeDir' => $relativeDirectory !== '' ? $relativeDirectory : null,
        ]);
    }

    public function downloadToAbsolutePath(string $megaPath, string $absoluteDestPath, ?string $generatedFileName = null): MegaUploadResult
    {
        $payload = [
            'megaPath' => $megaPath !== '' ? $megaPath : null,
            'destAbsolutePath' => $absoluteDestPath,
        ];
        if ($generatedFileName !== null && $generatedFileName !== '') {
            $payload['generatedFileName'] = $generatedFileName;
        }

        return $this->runNode('download', $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function runNode(string $action, array $payload): MegaUploadResult
    {
        $email = trim((string) config('services.mega.email', ''));
        $password = (string) config('services.mega.password', '');
        if ($email === '' || $password === '') {
            return new MegaUploadResult(false, null, null, 'MEGA_EMAIL / MEGA_PASSWORD not configured.');
        }

        $nodeBinary = trim((string) config('services.mega.node_binary', ''));
        $binary = $nodeBinary !== '' ? $nodeBinary : 'node';
        $userAgent = trim((string) config('services.mega.user_agent', 'BusKotorArchive/1.0'));
        if ($userAgent === '') {
            $userAgent = 'BusKotorArchive/1.0';
        }

        $script = base_path('scripts/mega-archive.js');
        if (! is_file($script)) {
            return new MegaUploadResult(false, null, null, 'mega-archive.js missing.');
        }

        try {
            $result = Process::path(base_path())
                ->timeout(900)
                ->env([
                    'MEGA_EMAIL' => $email,
                    'MEGA_PASSWORD' => $password,
                    'MEGA_BASE_FOLDER' => (string) config('services.mega.base_folder', 'bus.kotor'),
                    'MEGA_USER_AGENT' => $userAgent,
                ])
                ->input(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES))
                ->run([$binary, $script, $action]);
        } catch (Throwable $e) {
            Log::channel('payments')->warning('mega_node_exception', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);

            return new MegaUploadResult(false, null, null, $e->getMessage());
        }

        if (! $result->successful()) {
            $stderr = $result->errorOutput();
            $stdout = $result->output();
            Log::channel('payments')->warning('mega_node_process_failed', [
                'action' => $action,
                'exit_code' => $result->exitCode(),
                'stderr_preview' => mb_substr($stderr, 0, 500),
                'stdout_preview' => mb_substr($stdout, 0, 500),
            ]);

            return new MegaUploadResult(false, null, null, 'MEGA process failed: '.mb_substr($stderr !== '' ? $stderr : $stdout, 0, 400));
        }

        $out = trim($result->output());
        if ($out === '') {
            return new MegaUploadResult(false, null, null, 'Empty MEGA script output.');
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return new MegaUploadResult(false, null, null, 'Invalid JSON from MEGA script.');
        }

        if (! empty($decoded['ok'])) {
            return new MegaUploadResult(
                true,
                isset($decoded['mega_node_id']) ? (string) $decoded['mega_node_id'] : null,
                isset($decoded['mega_path']) ? (string) $decoded['mega_path'] : null,
                null,
            );
        }

        return new MegaUploadResult(
            false,
            null,
            null,
            isset($decoded['error']) ? (string) $decoded['error'] : 'Unknown MEGA error',
        );
    }
}
