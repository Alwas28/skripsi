<?php

namespace App\Providers;

use App\Models\AuditLog;
use App\Notifications\Channels\WhatsAppChannel;
use App\Observers\AuditObserver;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Notification::extend('whatsapp', fn () => new WhatsAppChannel);

        foreach (array_keys(AuditLog::MODULES) as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
