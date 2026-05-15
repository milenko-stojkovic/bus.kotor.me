<?php

namespace App\Console\Commands;

use App\Services\ExternalArchive\MegaDiagnoseService;
use Illuminate\Console\Command;

class MegaDiagnoseCommand extends Command
{
    protected $signature = 'files:mega-diagnose';

    protected $description = 'Verify MEGA login and base folder resolution (no uploads; password never printed)';

    public function handle(MegaDiagnoseService $diagnose): int
    {
        $email = trim((string) config('services.mega.email', ''));
        $password = (string) config('services.mega.password', '');
        $baseFolder = (string) config('services.mega.base_folder', 'bus.kotor');
        $nodeBinary = trim((string) config('services.mega.node_binary', ''));
        $binaryDisplay = $nodeBinary !== '' ? $nodeBinary : 'node';
        $userAgent = trim((string) config('services.mega.user_agent', 'BusKotorArchive/1.0'));
        if ($userAgent === '') {
            $userAgent = 'BusKotorArchive/1.0';
        }

        $this->info('MEGA diagnose');
        $this->line(str_repeat('=', 40));
        $this->newLine();
        $this->line('Configuration (secrets redacted):');
        $this->line('  Email: '.($email !== '' ? $this->maskEmail($email) : '(empty)'));
        $this->line('  Password: '.($password !== '' ? 'configured (not shown)' : '(missing)'));
        $this->line('  Base folder: '.$baseFolder);
        $this->line('  Node binary: '.$binaryDisplay);
        $this->line('  User-Agent: '.$userAgent);
        $this->newLine();

        $result = $diagnose->run();

        $this->line('--- Node script result ---');
        $this->line('  ok: '.($result['ok'] ? 'true' : 'false'));
        $this->line('  email_present: '.($result['email_present'] ? 'true' : 'false'));
        $this->line('  password_present: '.($result['password_present'] ? 'true' : 'false'));
        $this->line('  base_folder: '.($result['base_folder'] ?? ''));
        $this->line('  user_agent: '.($result['user_agent'] ?? ''));
        $this->line('  node_version: '.($result['node_version'] ?? '(null)'));
        $this->line('  login_ok: '.($result['login_ok'] ? 'true' : 'false'));
        $this->line('  folder_found: '.($result['folder_found'] ? 'true' : 'false'));
        $sample = $result['root_children_sample'] ?? [];
        $this->line('  root_children_sample: '.(count($sample) > 0 ? implode(', ', $sample) : '(empty)'));
        $err = (string) ($result['error'] ?? '');
        $this->line('  error: '.($err !== '' ? $err : '(none)'));
        $this->newLine();

        if (! ($result['email_present'] ?? false) || ! ($result['password_present'] ?? false)) {
            $this->error('MEGA credentials missing or incomplete in config. Set MEGA_EMAIL and MEGA_PASSWORD in .env.');

            return self::FAILURE;
        }

        if (! ($result['login_ok'] ?? false)) {
            $this->error('Login failed. Check MEGA_EMAIL / MEGA_PASSWORD and network access.');

            return self::FAILURE;
        }

        if (! ($result['folder_found'] ?? false)) {
            $this->warn('Folder missing: base folder "'.$baseFolder.'" was not found under your MEGA root (diagnose does not create it).');

            return self::FAILURE;
        }

        $this->info('Success: MEGA login works and base folder "'.$baseFolder.'" exists.');

        return self::SUCCESS;
    }

    private function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '(empty)';
        }

        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }

        [$local, $domain] = $parts;
        $first = $local !== '' ? mb_substr($local, 0, 1) : '?';

        return $first.'***@'.$domain;
    }
}
