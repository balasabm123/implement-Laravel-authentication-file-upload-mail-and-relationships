<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;


class PaymentGateway extends ServiceProvider
{
       public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            return new PaymentGateway();
        });
    }

    public function boot(): void
    {
        //
    }
}
