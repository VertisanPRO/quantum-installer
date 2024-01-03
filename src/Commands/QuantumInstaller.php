<?php

namespace Wemx\Quantum\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class QuantumInstaller extends Command
{
    protected $description = 'Install Quantum';

    protected $signature = 'quantum:install {license_key?}';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->info("
        ======================================
        |||         Quantum © 2024         |||
        |||         By VertisanPRO         |||
        ======================================
        ");

        if (!$this->confirm('Quantum is in early stage, it is unrecommended to use in production, do you wish to continue?', false)) {
            $this->info('Installation has been cancelled.');
            exit;
        }

        $this->sshUser();

        $license_key = $this->argument('license_key') ?? $this->ask("Please enter your license key", 'cancel');
        $domain = $this->getDomain();

        $this->info('Attempting to connect...');

        $response = Http::post('https://proxied.host/api/v1/licenses/public/download', [
            'packages' => 'Quantum',
            'license' => $license_key,
            'domain' => $domain,
            'resource_name' => 'quantum',
        ]);

        if (isset($response['success']) and !$response['success']) {
            $this->newLine();

            foreach ($response['errors'] as $error) {
                $this->error($error[0]);
                $this->newLine();
            }

            exit;
        }

        if (!isset($response['success']) or !isset($response['download_url'])) {
            $this->error('Something went wrong, please try again later.');
            exit;
        }

        shell_exec("curl -s -L -o quantum.zip {$response['download_url']}");
        shell_exec("unzip -o quantum.zip -d quantum");
        shell_exec("rm -rf quantum.zip");

        $this->info('Installation Complete');
    }

    private function ip()
    {
        $ipAddress = exec("curl -s ifconfig.me");

        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $this->newLine(2);
            $this->info("Failed to automatically retrieve IP Address");
            $ipAddress = $this->ask("Please enter this machines IP Address");
        }

        return $ipAddress;
    }

    private function getOption($key, $default = null)
    {
        return ($this->option($key)) ? $this->option($key) : $default;
    }

    private function sshUser()
    {
        $SshUser = exec('whoami');
        if (isset($SshUser) and $SshUser !== "root") {
            $this->error('
      We have detected that you are not logged in as a root user.
      To run the auto-quantum, it is recommended to login as root user.
      If you are not logged in as root, some processes may fail to setup
      To login as root SSH user, please type the following command: sudo su
      and proceed to re-run the quantum.
      alternatively you can contact your provider for ROOT user login for your machine.
      ');

            if ($this->confirm('Stop the installation?', true)) {
                $this->info('Installation has been cancelled.');
                exit;
            }
        }
    }

    protected function getDomain()
    {
        // check if config.appurl is a valid URL
        if (!filter_var(config('app.url'), FILTER_VALIDATE_URL)) {
            $this->error("App URL is not a valid URL in your .env file, it should be of format http://domain.com or https://domain.com");
            exit;
        }

        $domain = parse_url(config('app.url'), PHP_URL_HOST);

        return $domain;
    }
}
