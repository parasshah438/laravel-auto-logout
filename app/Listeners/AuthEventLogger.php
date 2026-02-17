<?php

namespace App\Listeners;

use App\Models\UserLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AuthEventLogger
{
    /**
     * Flag to prevent duplicate logout logging.
     * Set to true when logout is already logged by another handler
     * (e.g. beacon controller, inactivity middleware).
     */
    public static bool $skipLogoutLog = false;

    /**
     * Flag to prevent duplicate login logging within the same request.
     */
    public static bool $loginLogged = false;

    /**
     * Handle user login events.
     * Uses a static flag to ensure only one login log per request.
     */
    public function handleLogin(Login $event): void
    {
        if (static::$loginLogged) {
            return;
        }

        static::$loginLogged = true;

        UserLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'log_name' => 'login',
            'date' => now(),
        ]);
    }

    /**
     * Handle user logout events (manual logout only).
     * Skipped when logout is already logged by beacon or inactivity middleware.
     * Uses $skipLogoutLog flag to also prevent duplicate logs in same request.
     */
    public function handleLogout(Logout $event): void
    {
        if (static::$skipLogoutLog) {
            return;
        }

        static::$skipLogoutLog = true;

        if ($event->user) {
            UserLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'log_name' => 'manual_logout',
                'date' => now(),
            ]);
        }
    }
}
