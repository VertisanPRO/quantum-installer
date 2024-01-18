<?php

namespace Wemx\Quantum\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Helper\ProgressBar;

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

        $user = 'www-data';
        $group = 'www-data';
        if ($this->input->isInteractive()) {
            if (is_null($this->option('user'))) {
                $userDetails = posix_getpwuid(fileowner('public'));
                $user = $userDetails['name'] ?? 'www-data';

                if (!$this->confirm("Your webserver user has been detected as <fg=blue>[{$user}]:</> is this correct?", true)) {
                    $user = $this->anticipate(
                        'Please enter the name of the user running your webserver process. This varies from system to system, but is generally "www-data", "nginx", or "apache".',
                        [
                            'www-data',
                            'nginx',
                            'apache',
                        ]
                    );
                }
            }

            if (is_null($this->option('group'))) {
                $groupDetails = posix_getgrgid(filegroup('public'));
                $group = $groupDetails['name'] ?? 'www-data';

                if (!$this->confirm("Your webserver group has been detected as <fg=blue>[{$group}]:</> is this correct?", true)) {
                    $group = $this->anticipate(
                        'Please enter the name of the group running your webserver process. Normally this is the same as your user.',
                        [
                            'www-data',
                            'nginx',
                            'apache',
                        ]
                    );
                }
            }

            if (!$this->confirm('Are you sure you want to run the upgrade process for your Panel?')) {
                $this->warn('Upgrade process terminated by user.');

                return;
            }
        }

        ini_set('output_buffering', '0');
        $bar = $this->output->createProgressBar(10);
        $bar->start();

        $this->withProgress($bar, function () {
            $this->line("\$upgrader> curl -L \"{$this->getUrl()}\" | tar -xzv");
            $process = Process::fromShellCommandline("curl -L \"{$this->getUrl()}\" | tar -xzv");
            $process->run(function ($type, $buffer) {
                $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
            });
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan down');
            $this->call('down');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> chmod -R 755 storage bootstrap/cache');
            $process = new Process(['chmod', '-R', '755', 'storage', 'bootstrap/cache']);
            $process->run(function ($type, $buffer) {
                $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
            });
        });

        $this->withProgress($bar, function () {
            $command = ['composer', 'install', '--no-ansi'];
            if (config('app.env') === 'production' && !config('app.debug')) {
                $command[] = '--optimize-autoloader';
                $command[] = '--no-dev';
            }

            $this->line('$upgrader> ' . implode(' ', $command));
            $process = new Process($command);
            $process->setTimeout(10 * 60);
            $process->run(function ($type, $buffer) {
                $this->line($buffer);
            });
        });

        /** @var \Illuminate\Foundation\Application $app */
        $app = require __DIR__ . '/../../../bootstrap/app.php';
        /** @var \Pterodactyl\Console\Kernel $kernel */
        $kernel = $app->make(Kernel::class);
        $kernel->bootstrap();
        $this->setLaravel($app);

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan view:clear');
            $this->call('view:clear');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan config:clear');
            $this->call('config:clear');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan migrate --force --seed');
            $this->call('migrate', ['--force' => true, '--seed' => true]);
        });

        $this->withProgress($bar, function () use ($user, $group) {
            $this->line("\$upgrader> chown -R {$user}:{$group} *");
            $process = Process::fromShellCommandline("chown -R {$user}:{$group} *", $this->getLaravel()->basePath());
            $process->setTimeout(10 * 60);
            $process->run(function ($type, $buffer) {
                $this->{$type === Process::ERR ? 'error' : 'line'}($buffer);
            });
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan queue:restart');
            $this->call('queue:restart');
        });

        $this->withProgress($bar, function () {
            $this->line('$upgrader> php artisan up');
            $this->call('up');
        });

        $this->newLine(2);
        $this->info('Pterodactyl has been reverted to default and updated to the latest version');
    }

    protected function withProgress(ProgressBar $bar, \Closure $callback)
    {
        $bar->clear();
        $callback();
        $bar->advance();
        $bar->display();
    }
}
