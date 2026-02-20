<?php

use App\Http\Controllers\AutoLogoutController;
use App\Http\Controllers\Auth\LoginController;
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
Route::post('/otp/request', [LoginController::class, 'requestOtp'])->name('otp.request');
Route::get('/otp/verify', [LoginController::class, 'showOtpForm'])->name('otp.form');
Route::post('/otp/verify', [LoginController::class, 'verifyOtp'])->name('otp.verify');
Route::post('/otp/resend', [LoginController::class, 'resendOtp'])->name('otp.resend');

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');
