<?php

namespace App\Providers;

use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\SmsProviderInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProviderInterface::class, function () {
            if (app()->environment('production')) {
                throw new \RuntimeException('Aucun fournisseur SMS réel n’est configuré pour la production.');
            }

            return new LogSmsProvider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
