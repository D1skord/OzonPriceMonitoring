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

        if (! is_dir($profilePath)) {
            mkdir($profilePath, 0775, true);
        }

        foreach (['SingletonLock', 'SingletonSocket', 'SingletonCookie'] as $lockFile) {
            $path = $profilePath.DIRECTORY_SEPARATOR.$lockFile;

            if (is_link($path) || is_file($path)) {
                unlink($path);
            }
        }

        $this->info('Open noVNC and log in to Ozon. Stop this command after login is complete.');

        $environment = [
            'OZON_PROFILE_PATH' => $profilePath,
            'DISPLAY' => getenv('DISPLAY') ?: ':99',
        ];
        $proxyServer = config('services.ozon.proxy_server');

        if (is_string($proxyServer) && $proxyServer !== '') {
            $environment['OZON_PROXY_SERVER'] = $proxyServer;
            $environment['OZON_PROXY_USERNAME'] = (string) config('services.ozon.proxy_username');
            $environment['OZON_PROXY_PASSWORD'] = (string) config('services.ozon.proxy_password');
        }

        $process = new Process([
            'node',
            base_path('resources/playwright/ozon-login-profile.mjs'),
        ], base_path(), $environment);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
