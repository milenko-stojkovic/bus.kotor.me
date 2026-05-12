<?php

namespace Tests\Unit\Services\Limo;

use App\Services\Limo\TesseractOcrRunner;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class TesseractOcrRunnerDiagnosticsTest extends TestCase
{
    #[Test]
    public function build_diagnostics_includes_command_exit_code_stderr_and_input_file_facts(): void
    {
        $p = new Process([PHP_BINARY, '-r', 'fwrite(STDERR, "tess-err-line"); exit(4);']);
        $p->run();
        $this->assertFalse($p->isSuccessful());

        $imgPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'spaced dir'.DIRECTORY_SEPARATOR.'plate test.png';
        @mkdir(dirname($imgPath), 0777, true);
        file_put_contents($imgPath, 'not-a-real-png');

        $msg = TesseractOcrRunner::buildDiagnosticsForFailedProcess($p, $imgPath);

        $this->assertStringContainsString('Tesseract process failed.', $msg);
        $this->assertStringContainsString('command:', $msg);
        $this->assertStringContainsString('exit_code: 4', $msg);
        $this->assertStringContainsString('input_basename: plate test.png', $msg);
        $this->assertStringContainsString('input_exists: yes', $msg);
        $this->assertStringContainsString('input_size_bytes:', $msg);
        $this->assertStringContainsString('stderr:', $msg);
        $this->assertStringContainsString('tess-err-line', $msg);
        $this->assertStringContainsString('stdout_preview:', $msg);

        @unlink($imgPath);
        @rmdir(dirname($imgPath));
    }

    #[Test]
    public function build_diagnostics_reports_missing_input_file(): void
    {
        $p = new Process([PHP_BINARY, '-r', 'exit(2);']);
        $p->run();

        $missing = sys_get_temp_dir().DIRECTORY_SEPARATOR.'no-such-limo-ocr-'.uniqid('', true).'.png';
        $msg = TesseractOcrRunner::buildDiagnosticsForFailedProcess($p, $missing);

        $this->assertStringContainsString('input_exists: no', $msg);
        $this->assertStringContainsString('input_size_bytes: n/a', $msg);
    }

    #[Test]
    public function process_command_line_escapes_paths_with_spaces(): void
    {
        $pathWithSpaces = 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe';
        $img = 'D:\\temp test\\plate 1.png';
        $process = new Process([$pathWithSpaces, $img, 'stdout', '-l', 'eng', '--psm', '7']);
        $line = $process->getCommandLine();

        $this->assertStringContainsString('Program Files', $line);
        $this->assertStringContainsString('Tesseract-OCR', $line);
        $this->assertStringContainsString('temp test', $line);
        $this->assertStringContainsString('plate 1.png', $line);
    }
}