<?php

namespace Wemx\Quantum;

use Illuminate\Support\ServiceProvider;
use Wemx\Quantum\Commands\QuantumInstaller;
use Wemx\Quantum\Commands\QuantumUninstaller;
use Wemx\Quantum\Commands\QuantumBuilding;

class CommandsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            QuantumInstaller::class,
            QuantumUninstaller::class,
            QuantumBuilding::class,
        ]);
    }

    public function register()
    {

    }
}
