<?php

namespace IpsiLaravel;

use Illuminate\Support\ServiceProvider;

class IpsiPaymentServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/ipsi.php' => config_path('ipsi.php'),
        ], 'ipsi-config');
    }
    
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ipsi.php', 'ipsi'
        );
    }
}
