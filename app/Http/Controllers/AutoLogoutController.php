<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AutoLogoutController extends Controller
{
    /**
     * Handle beacon logout request (tab close / browser close).
     * Called via navigator.sendBeacon() from the frontend.
     *
     * Note: sendBeacon sends as application/json, and during page unload
     * the session/cookie may not be reliably available, so we accept
     * user_id in the payload and verify the user exists.
     */
    public function beaconLogout(Request $request): JsonResponse
    {
        $data = $request->json()->all();
        $userId = $data['user_id'] ?? null;
        $reason = $data['reason'] ?? 'tab_or_browser_close';

        if (!$userId) {
            return response()->json(['status' => 'no_user'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            return response()->json(['status' => 'user_not_found'], 404);
        }

        // Store log before logout
        UserLog::create([
            'user_id' => $user->id,
            'log_name' => $reason,
            'date' => now(),
        ]);

        // Prevent duplicate log from Logout event listener
        \App\Listeners\AuthEventLogger::$skipLogoutLog = true;

        // Logout the user - invalidate all sessions for this user
        if (Auth::check() && Auth::id() === $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            // If session isn't available (page already unloading),
            // delete user's sessions from DB to force logout
            if (config('session.driver') === 'database') {
                \DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }

        return response()->json(['status' => 'logged_out']);
    }
}
