<?php

namespace App\Http\Middleware;

use App\Models\UserLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutOnInactivity
{
    /**
     * Handle an incoming request.
     *
     * Checks if the authenticated user has been inactive for longer than
     * the configured session lifetime. If so, logs user activity,
     * logs out the user, and redirects to login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = session('last_activity_time');
            $timeout = config('session.lifetime') * 60; // convert minutes to seconds

            if ($lastActivity && (time() - $lastActivity) > $timeout) {
                $user = Auth::user();

                // Store log before logout
                UserLog::create([
                    'user_id' => $user->id,
                    'log_name' => 'inactivity_timeout',
                    'date' => now(),
                ]);

                // Prevent duplicate log from Logout event listener
                \App\Listeners\AuthEventLogger::$skipLogoutLog = true;

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('message', 'You have been logged out due to inactivity.');
            }

            // Update last activity time
            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}
