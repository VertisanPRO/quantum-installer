<?php

namespace Wemx\Quantum\Commands;

use Illuminate\Console\Command;
use function Laravel\Prompts\info;

class QuantumBuilding extends Command
{
    protected $description = 'Install yarn properly and build assets';

    protected $signature = 'quantum:build {--no-copyright}';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        if (!$this->option('no-copyright'))
            info('
            ======================================
            |||         Quantum © 2024         |||
            |||         By VertisanPRO         |||
            ======================================
            ');

        function installedUbuntu($packageName)
        {
            $output = null;
            $code = null;
            exec("dpkg-query -W -f='\${Status}' $packageName 2>/dev/null", $output, $code);

            return ($code === 0 && strpos($output[0], 'install ok installed') !== false);
        }

        function installedRHEL($packageName)
        {
            $output = null;
            $code = null;
            exec("rpm -q $packageName 2>/dev/null", $output, $code);

            return ($code === 0);
        }

        if (installedUbuntu('cmdtest')) {
            exec('apt remove cmdtest -y');
        } elseif (installedRHEL('cmdtest')) {
            exec('yum remove cmdtest -y');
        }

        $output = null;
        $code = null;
        exec('node -v 2>/dev/null', $output, $code);

        if ($code === 0) {
            if (version_compare(trim($output[0]), 'v17', '>'))
                putenv('NODE_OPTIONS=--openssl-legacy-provider');
        } else {
            if (file_exists('/etc/redhat-release')) {
                exec('sudo yum install https://rpm.nodesource.com/pub_16.x/nodistro/repo/nodesource-release-nodistro-1.noarch.rpm -y');
                system('sudo yum install -y nodejs');
            } else {
                exec('sudo mkdir -p /etc/apt/keyrings');
                exec('curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | sudo gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg');
                exec('echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_16.x nodistro main" | sudo tee /etc/apt/sources.list.d/nodesource.list');
                system('sudo apt-get update');
                system('sudo apt-get install -y nodejs');
            }
        }

        $output = null;
        $code = null;
        exec('yarn --version 2>/dev/null', $output, $code);

        if ($code === 0) {
            system('yarn && yarn build:production');
        } else {
            exec('npm install -g yarn');
            system('yarn && yarn build:production');
        }

        if ($code === 0)
            return 0;

        return 1;
    }
}
