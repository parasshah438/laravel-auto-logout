<?php

use App\Http\Controllers\AutoLogoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Auto Logout - Beacon Route
|--------------------------------------------------------------------------
| This route handles tab/browser close logout via navigator.sendBeacon().
| It must be a POST route and accessible to authenticated users.
*/
Route::post('/auto-logout/beacon', [AutoLogoutController::class, 'beaconLogout'])
    ->name('auto-logout.beacon');

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
