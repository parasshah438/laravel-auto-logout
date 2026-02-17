# Auto Logout - Laravel 12

## Overview

This feature automatically logs out users in 3 scenarios and stores a log entry in the `user_logs` table **before** every logout.

| # | Scenario | `log_name` in DB |
|---|----------|-----------------|
| 1 | User closes the browser tab | `tab_or_browser_close` |
| 2 | User closes the browser | `tab_or_browser_close` |
| 3 | User is idle (no activity) for session lifetime | `inactivity_timeout` |
| 4 | User clicks Logout button manually | `manual_logout` |
| 5 | User logs in | `login` |

---

## `user_logs` Table Structure

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint (PK) | Auto increment |
| `user_id` | foreignId | References `users.id` |
| `log_name` | string | Event name (`login`, `manual_logout`, `tab_or_browser_close`, `inactivity_timeout`) |
| `date` | timestamp | When the event occurred |

---

## How It Works - Process Flow

### 1. Tab Close / Browser Close Logout

```
User closes tab/browser
    ↓
Browser fires "beforeunload" JavaScript event
    ↓
auto-logout.blade.php JS sends navigator.sendBeacon()
    → POST /auto-logout/beacon (JSON: { user_id, reason })
    ↓
AutoLogoutController@beaconLogout
    → Finds user by user_id
    → Creates UserLog (log_name: "tab_or_browser_close")
    → Sets AuthEventLogger::$skipLogoutLog = true (prevent duplicate)
    → Auth::logout() + session invalidate
    → OR deletes sessions from DB if session not available
```

### 2. Inactivity Timeout Logout

```
User makes any web request
    ↓
AutoLogoutOnInactivity middleware runs
    → Checks session('last_activity_time') vs config('session.lifetime')
    ↓
If expired:
    → Creates UserLog (log_name: "inactivity_timeout")
    → Sets AuthEventLogger::$skipLogoutLog = true (prevent duplicate)
    → Auth::logout() + session invalidate
    → Redirects to login page with message
    ↓
If not expired:
    → Updates session('last_activity_time') to now
    → Continues to next middleware
```

### 3. Manual Logout (Click Logout Button)

```
User clicks Logout button
    ↓
POST /logout (Laravel default auth route)
    ↓
Laravel fires Logout event
    ↓
AuthEventLogger@handleLogout (registered in AppServiceProvider)
    → Checks $skipLogoutLog flag (false for manual logout)
    → Creates UserLog (log_name: "manual_logout")
```

### 4. Login Logging

```
User submits login form
    ↓
Laravel fires Login event
    ↓
AuthEventLogger@handleLogin (registered in AppServiceProvider)
    → Creates UserLog (log_name: "login")
```

---

## Files For Production

### NEW Files (Upload These)

| # | File Path | Purpose |
|---|-----------|---------|
| 1 | `database/migrations/2026_02_17_000001_create_user_logs_table.php` | Migration to create `user_logs` table |
| 2 | `app/Models/UserLog.php` | UserLog Eloquent model |
| 3 | `app/Http/Controllers/AutoLogoutController.php` | Handles beacon logout POST request |
| 4 | `app/Http/Middleware/AutoLogoutOnInactivity.php` | Middleware to check inactivity timeout |
| 5 | `app/Listeners/AuthEventLogger.php` | Event listener for login/logout logging |
| 6 | `resources/views/components/auto-logout.blade.php` | JavaScript for tab/browser close detection |

### MODIFIED Files (Update These)

| # | File Path | What Changed |
|---|-----------|-------------|
| 7 | `app/Models/User.php` | Added `logs()` HasMany relationship |
| 8 | `resources/views/layouts/app.blade.php` | Added `@include('components.auto-logout')` before `</body>` |
| 9 | `routes/web.php` | Added beacon POST route |
| 10 | `bootstrap/app.php` | Registered `AutoLogoutOnInactivity` middleware + CSRF exception |
| 11 | `app/Providers/AppServiceProvider.php` | Registered Login/Logout event listeners |

---

## Production Deployment Steps

```bash
# 1. Upload all files listed above to their respective paths

# 2. Run migration to create user_logs table
php artisan migrate

# 3. Clear all caches
php artisan optimize:clear

# 4. (Optional) Set session lifetime in .env (default is 120 minutes)
# SESSION_LIFETIME=120
```

---

## File Details & Code

### 1. `database/migrations/2026_02_17_000001_create_user_logs_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('log_name');
            $table->timestamp('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_logs');
    }
};
```

---

### 2. `app/Models/UserLog.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'log_name',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

---

### 3. `app/Http/Controllers/AutoLogoutController.php`

```php
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

        // Logout the user
        if (Auth::check() && Auth::id() === $user->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } else {
            // Delete user's sessions from DB if session not available
            if (config('session.driver') === 'database') {
                \DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        }

        return response()->json(['status' => 'logged_out']);
    }
}
```

---

### 4. `app/Http/Middleware/AutoLogoutOnInactivity.php`

```php
<?php

namespace App\Http\Middleware;

use App\Models\UserLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AutoLogoutOnInactivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $lastActivity = session('last_activity_time');
            $timeout = config('session.lifetime') * 60;

            if ($lastActivity && (time() - $lastActivity) > $timeout) {
                $user = Auth::user();

                UserLog::create([
                    'user_id' => $user->id,
                    'log_name' => 'inactivity_timeout',
                    'date' => now(),
                ]);

                \App\Listeners\AuthEventLogger::$skipLogoutLog = true;

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('message', 'You have been logged out due to inactivity.');
            }

            session(['last_activity_time' => time()]);
        }

        return $next($request);
    }
}
```

---

### 5. `app/Listeners/AuthEventLogger.php`

```php
<?php

namespace App\Listeners;

use App\Models\UserLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AuthEventLogger
{
    public static bool $skipLogoutLog = false;

    public function handleLogin(Login $event): void
    {
        UserLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'log_name' => 'login',
            'date' => now(),
        ]);
    }

    public function handleLogout(Logout $event): void
    {
        if (static::$skipLogoutLog) {
            return;
        }

        if ($event->user) {
            UserLog::create([
                'user_id' => $event->user->getAuthIdentifier(),
                'log_name' => 'manual_logout',
                'date' => now(),
            ]);
        }
    }
}
```

---

### 6. `resources/views/components/auto-logout.blade.php`

```blade
@auth
<script>
(function() {
    const BEACON_URL = "{{ route('auto-logout.beacon') }}";
    let beaconSent = false;

    function sendLogoutBeacon(reason) {
        if (beaconSent) return;
        beaconSent = true;

        const data = new Blob(
            [JSON.stringify({ reason: reason, user_id: {{ Auth::id() }} })],
            { type: 'application/json' }
        );

        navigator.sendBeacon(BEACON_URL, data);

        setTimeout(function() { beaconSent = false; }, 2000);
    }

    window.addEventListener('beforeunload', function(e) {
        sendLogoutBeacon('tab_or_browser_close');
    });
})();
</script>
@endauth
```

---

### 7. `app/Models/User.php` — Added Code

```php
// Add this import at top
use Illuminate\Database\Eloquent\Relations\HasMany;

// Add this method inside the User class
public function logs(): HasMany
{
    return $this->hasMany(UserLog::class);
}
```

---

### 8. `resources/views/layouts/app.blade.php` — Added Code

Add before `</body>`:

```blade
    @include('components.auto-logout')
</body>
```

---

### 9. `routes/web.php` — Added Code

```php
use App\Http\Controllers\AutoLogoutController;

Route::post('/auto-logout/beacon', [AutoLogoutController::class, 'beaconLogout'])
    ->name('auto-logout.beacon');
```

---

### 10. `bootstrap/app.php` — Added Code

```php
use App\Http\Middleware\AutoLogoutOnInactivity;

->withMiddleware(function (Middleware $middleware): void {
    $middleware->web(append: [
        AutoLogoutOnInactivity::class,
    ]);

    $middleware->validateCsrfTokens(except: [
        'auto-logout/beacon',
    ]);
})
```

---

### 11. `app/Providers/AppServiceProvider.php` — Added Code

```php
use App\Listeners\AuthEventLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

// Inside boot() method:
Event::listen(Login::class, [AuthEventLogger::class, 'handleLogin']);
Event::listen(Logout::class, [AuthEventLogger::class, 'handleLogout']);
```

---

## .env Configuration

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

`SESSION_LIFETIME` controls inactivity timeout (in minutes). Default is 120 minutes (2 hours).
