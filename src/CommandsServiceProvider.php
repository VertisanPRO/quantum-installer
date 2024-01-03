<?php

namespace Wemx\Quantum;

use Illuminate\Support\ServiceProvider;
use Wemx\Quantum\Commands\QuantumInstaller;

class CommandsServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->commands([
            QuantumInstaller::class,
        ]);
    }

    public function register()
    {

    }
}
