<?php

namespace Wemx\Quantum\Commands;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use function Laravel\Prompts\{progress, text, select, confirm, info, warning, spin, error};

class QuantumInstaller extends Command
{
    protected $description = 'Install Quantum';

    protected $signature = 'quantum:install {license_key?} {--force}';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        info("
        ======================================
        |||         Quantum © 2024         |||
        |||         By VertisanPRO         |||
        ======================================
        ");

        $who = exec('whoami');
        if (isset($who) and $who !== "root") {
            error('
                We have detected that you are not logged in as a root user.
                To run the installer, it is recommended to login as root user.
                If you are not logged in as root, some processes may fail to setup
                To login as the root user, please type the following command: su -
                and proceed to re-run the installer.
                Alternatively you can contact your provider for the root login for your machine.
            ');

            $confirm = confirm(
                label: 'Do you wish to continue?',
                default: false,
            );

            if (!$confirm) {
                warning('Installation has been cancelled');
                exit;
            }
        }

        $license_key = $this->argument('license_key') ?? text(
            label: 'Please enter your license key',
            required: true,
        );

        $domain = $this->getDomain();

        $response = spin(
            fn() => Http::post('https://proxied.host/api/v1/licenses/public/download', [
                'packages' => 'Quantum',
                'license' => $license_key,
                'domain' => $domain,
                'resource_name' => 'quantum',
            ]),
            'Attempting to connect...'
        );

        if (isset($response['success']) and !$response['success']) {
            foreach ($response['errors'] as $error)
                error($error[0]);

            return;
        }

        if (!isset($response['success']) or !isset($response['download_url'])) {
            error('Something went wrong, please try again later.');
            return;
        }

        $userDetails = posix_getpwuid(fileowner('public'));
        $user = $userDetails['name'] ?? 'www-data';

        $confirm = confirm(
            label: "Your webserver user has been detected as <fg=green>[{$user}]:</> is this correct?",
            default: true,
        );

        if (!$confirm) {
            $user = select(
                label: 'Please enter the name of the user running your webserver process. This varies from system to system, but is generally "www-data", "nginx", or "apache".',
                options: [
                    'www-data' => 'www-data',
                    'nginx' => 'nginx',
                    'apache' => 'apache',
                    'own' => 'Your own user (type after you choose this)'
                ],
                default: 'www-data'
            );

            if ($user === 'own')
                $user = text('Please enter the name of the user running your webserver process');
        }

        $groupDetails = posix_getgrgid(filegroup('public'));
        $group = $groupDetails['name'] ?? 'www-data';

        $confirm = confirm(
            label: "Your webserver group has been detected as <fg=green>[{$group}]:</> is this correct?",
            default: true,
        );

        if (!$confirm) {
            $user = select(
                label: 'Please enter the name of the group running your webserver process. Normally this is the same as your user.',
                options: [
                    'www-data' => 'www-data',
                    'nginx' => 'nginx',
                    'apache' => 'apache',
                    'own' => 'Your own group (type after you choose this)'
                ],
                default: 'www-data'
            );

            if ($user === 'own')
                $user = text('Please enter the name of the group running your webserver process');
        }

        if (!$this->option('force')) {
            $confirm = confirm(
                label: 'Quantum is in alpha stage, it is unrecommended to use in production, do you wish to continue?',
                default: false,
            );

            if (!$confirm) {
                warning('Installation has been cancelled');
                return;
            }
        }

        $progress = progress(label: 'Installing Quantum', steps: 6);
        $progress->start();

        spin(
            fn() => $this->getQuantum($response),
            'Downloading latest stable version for Pterodactyl'
        );

        $progress->advance();

        spin(
            fn() => exec('chmod -R 755 storage/* bootstrap/cache'),
            'Setting correct permissions'
        );

        $progress->advance();

        spin(
            fn() => exec('php artisan view:clear && php artisan config:clear && php artisan optimize'),
            'Clearing cache'
        );

        $progress->advance();

        spin(
            fn() => exec('php artisan migrate --force'),
            'Migrating the database'
        );

        $progress->advance();

        $basePath = base_path();
        spin(
            fn() => exec("chown -R {$user}:{$group} {$basePath}/*"),
            'Setting correct permissions'
        );

        $progress->advance();

        spin(
            fn() => Artisan::call('quantum:build --no-copyright'),
            'Building Assets (This can take a few minutes)'
        );

        $progress->advance();

        info('Installation Complete');
    }

    private function ip()
    {
        $ipAddress = exec("curl -s ifconfig.me");

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            error("Failed to automatically retrieve IP Address");
            $ipAddress = $this->ask("Please enter this machines IP Address");
        }

        return $ipAddress;
    }

    private function getOption($key, $default = null)
    {
        return ($this->option($key)) ? $this->option($key) : $default;
    }

    protected function getDomain()
    {
        if (!filter_var(config('app.url'), FILTER_VALIDATE_URL)) {
            error("App URL is not a valid URL in your .env file, it should be of format http://domain.com or https://domain.com");
            return;
        }

        return parse_url(config('app.url'), PHP_URL_HOST);
    }


    private function getQuantum($response)
    {
        exec("curl -s -L -o quantum.zip {$response['download_url']}");
        exec("unzip -o quantum.zip");
        exec("rm -rf quantum.zip");
    }
}
