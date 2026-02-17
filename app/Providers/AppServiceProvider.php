<?php

namespace App\Providers;

use App\Listeners\AuthEventLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
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
        Schema::defaultStringLength(191);

        // Log login and manual logout events
        Event::listen(Login::class, [AuthEventLogger::class, 'handleLogin']);
        Event::listen(Logout::class, [AuthEventLogger::class, 'handleLogout']);
    }
}
