<?php

namespace BRISP\LivewireComponentRequests;

use BRISP\LivewireComponentRequests\Console\Commands\MakeLivewireComponentRequestCommand;
use Illuminate\Support\ServiceProvider;

class LivewireComponentRequestsServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                MakeLivewireComponentRequestCommand::class,
            ]);
        }
    }
}
