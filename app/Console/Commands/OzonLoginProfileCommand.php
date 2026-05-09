<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

final class OzonLoginProfileCommand extends Command
{
    protected $signature = 'ozon:login-profile';

    protected $description = 'Open Chromium with the persistent Ozon profile for manual login.';

    public function handle(): int
    {
        $profilePath = (string) config('services.ozon.profile_path');
        $chromePath = (string) config('services.ozon.chrome_path');

        if (! is_dir($profilePath)) {
            mkdir($profilePath, 0775, true);
        }

        $this->info('Open noVNC and log in to Ozon. Stop this command after login is complete.');

        $process = new Process([
            $chromePath,
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--user-data-dir='.$profilePath,
            'https://www.ozon.ru/my/favorites',
        ]);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
