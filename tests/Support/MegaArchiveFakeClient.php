<?php

namespace Tests\Support;

use App\Contracts\MegaArchiveClient;
use App\Services\ExternalArchive\MegaUploadResult;

final class MegaArchiveFakeClient implements MegaArchiveClient
{
    public int $uploadCalls = 0;

    public int $downloadCalls = 0;

    public ?string $lastDownloadGeneratedFileName = null;

    public ?string $lastUploadAbsolutePath = null;

    public ?int $lastUploadedBytes = null;

    public bool $uploadShouldFail = false;

    public bool $downloadShouldFail = false;

    /**
     * When non-empty, each upload consumes the next result (for retry tests).
     *
     * @var list<MegaUploadResult>|null
     */
    public ?array $uploadResultsQueue = null;

    /**
     * When non-empty, each download consumes the next result.
     *
     * @var list<MegaUploadResult>|null
     */
    public ?array $downloadResultsQueue = null;

    public function uploadLocalFile(string $absoluteLocalPath, string $generatedFileName): MegaUploadResult
    {
        if ($this->uploadResultsQueue !== null && $this->uploadResultsQueue !== []) {
            $this->uploadCalls++;
            $this->lastUploadAbsolutePath = $absoluteLocalPath;
            $this->lastUploadedBytes = is_file($absoluteLocalPath) ? (int) filesize($absoluteLocalPath) : null;
            /** @var MegaUploadResult */
            $next = array_shift($this->uploadResultsQueue);

            return $next;
        }

        $this->uploadCalls++;
        $this->lastUploadAbsolutePath = $absoluteLocalPath;
        $this->lastUploadedBytes = is_file($absoluteLocalPath) ? (int) filesize($absoluteLocalPath) : null;
        if ($this->uploadShouldFail) {
            return new MegaUploadResult(false, null, null, 'fake_upload_failed');
        }

        return new MegaUploadResult(true, 'fake-node-'.$this->uploadCalls, 'bus.kotor/'.$generatedFileName, null);
    }

    public function downloadToAbsolutePath(string $megaPathOrName, string $absoluteDestPath, ?string $generatedFileName = null): MegaUploadResult
    {
        if ($this->downloadResultsQueue !== null && $this->downloadResultsQueue !== []) {
            $this->downloadCalls++;
            $this->lastDownloadGeneratedFileName = $generatedFileName;
            /** @var MegaUploadResult */
            $next = array_shift($this->downloadResultsQueue);
            if ($next->ok) {
                $dir = dirname($absoluteDestPath);
                if (! is_dir($dir)) {
                    mkdir($dir, 0750, true);
                }
                file_put_contents($absoluteDestPath, 'restored-by-fake');
            }

            return $next;
        }

        $this->downloadCalls++;
        $this->lastDownloadGeneratedFileName = $generatedFileName;
        if ($this->downloadShouldFail) {
            return new MegaUploadResult(false, null, null, 'fake_download_failed');
        }

        $dir = dirname($absoluteDestPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($absoluteDestPath, 'restored-by-fake');

        return new MegaUploadResult(true, null, $megaPathOrName, null);
    }
}
