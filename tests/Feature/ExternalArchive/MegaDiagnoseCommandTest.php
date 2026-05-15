<?php

namespace Tests\Feature\ExternalArchive;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class MegaDiagnoseCommandTest extends TestCase
{
    public function test_mega_diagnose_does_not_print_password(): void
    {
        config([
            'services.mega.email' => 'ops@example.com',
            'services.mega.password' => 'SuperSecretMegaPassword-xyz',
            'services.mega.base_folder' => 'bus.kotor',
            'services.mega.node_binary' => '',
            'services.mega.user_agent' => 'BusKotorArchive/1.0',
        ]);

        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'ok' => true,
                    'email_present' => true,
                    'password_present' => true,
                    'base_folder' => 'bus.kotor',
                    'user_agent' => 'BusKotorArchive/1.0',
                    'node_version' => 'v22.0.0',
                    'login_ok' => true,
                    'folder_found' => true,
                    'root_children_sample' => ['dir:bus.kotor'],
                    'error' => '',
                ]),
                exitCode: 0,
            ),
        ]);

        $exit = Artisan::call('files:mega-diagnose');
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringNotContainsString('SuperSecretMegaPassword-xyz', $out);
        $this->assertStringNotContainsString('MEGA_PASSWORD', $out);
        $this->assertStringContainsString('User-Agent: BusKotorArchive/1.0', $out);
        $this->assertStringContainsString('user_agent: BusKotorArchive/1.0', $out);
    }

    public function test_mega_diagnose_handles_missing_config(): void
    {
        config([
            'services.mega.email' => '',
            'services.mega.password' => '',
            'services.mega.base_folder' => 'bus.kotor',
            'services.mega.node_binary' => '',
            'services.mega.user_agent' => 'BusKotorArchive/1.0',
        ]);

        Process::fake();

        $exit = Artisan::call('files:mega-diagnose');
        $out = Artisan::output();

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('MEGA credentials missing', $out);
        $this->assertStringContainsString('User-Agent: BusKotorArchive/1.0', $out);
        Process::assertNothingRan();
    }

    public function test_mega_diagnose_parses_json_result_from_process(): void
    {
        config([
            'services.mega.email' => 'ops@example.com',
            'services.mega.password' => 'x',
            'services.mega.base_folder' => 'my-archive',
            'services.mega.node_binary' => '',
            'services.mega.user_agent' => 'CustomArchive/9.9',
        ]);

        Process::fake([
            '*' => Process::result(
                output: json_encode([
                    'ok' => true,
                    'email_present' => true,
                    'password_present' => true,
                    'base_folder' => 'my-archive',
                    'user_agent' => 'CustomArchive/9.9',
                    'node_version' => 'v20.1.0',
                    'login_ok' => true,
                    'folder_found' => true,
                    'root_children_sample' => ['dir:my-archive', 'file:readme.txt'],
                    'error' => '',
                ]),
                exitCode: 0,
            ),
        ]);

        $exit = Artisan::call('files:mega-diagnose');
        $out = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('login_ok: true', $out);
        $this->assertStringContainsString('folder_found: true', $out);
        $this->assertStringContainsString('dir:my-archive', $out);
        $this->assertStringContainsString('Success:', $out);
        $this->assertStringContainsString('User-Agent: CustomArchive/9.9', $out);
        $this->assertStringContainsString('user_agent: CustomArchive/9.9', $out);
        Process::assertRan(function ($process) {
            if (($process->environment['MEGA_USER_AGENT'] ?? null) !== 'CustomArchive/9.9') {
                return false;
            }

            $cmd = $process->command;
            $line = is_array($cmd) ? implode(' ', $cmd) : (string) $cmd;

            return str_contains($line, 'mega-archive.js') && str_contains($line, 'diagnose');
        });
    }
}
