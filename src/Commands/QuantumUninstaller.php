<?php

namespace Wemx\Quantum\Commands;

use Illuminate\Console\Command;

class QuantumUninstaller extends Command
{
    protected $description = 'Uninstall Quantum';

    protected $signature = 'quantum:uninstall {--force}';

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

        if (!$this->option('force') && !$this->confirm('You are about to uninstall Quantum. This process will removes all third party changes and updates Pterodactyl to the latest available version. Are you sure you want to continue? ', false)) {
            $this->info('Installation has been cancelled.');
            exit;
        }

        $basePath = base_path();
        
        $this->info("Downloading latest stable version for Pterodactyl");
        shell_exec("curl -L https://github.com/pterodactyl/panel/releases/latest/download/panel.tar.gz | tar -xzv");
        shell_exec("chmod -R 755 storage/* bootstrap/cache");

        $this->info("Installing composer dependencies & Clearing cache");
        shell_exec('composer install --no-dev --optimize-autoloader --no-interaction');
        shell_exec('php artisan view:clear && php artisan config:clear');

        $this->info("Migrating and seeding the database");
        shell_exec('php artisan migrate --seed --force');

        $this->info("Setting correct permissions");
        shell_exec("chown -R www-data:www-data {$basePath}/* > /dev/null 2>&1");
        shell_exec("chown -R nginx:nginx {$basePath}/* > /dev/null 2>&1");
        shell_exec("chown -R apache:apache {$basePath}/* > /dev/null 2>&1");        

        $this->info("Restarting queue worker & cleaning up");
        shell_exec("php artisan queue:restart");

        $this->info('Pterodactyl has been reverted to default and updated to the latest version');
    }
}
