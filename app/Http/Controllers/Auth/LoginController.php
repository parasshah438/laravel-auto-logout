<?php

namespace App\Http\Controllers\Auth;

use App\Mail\OtpLoginMail;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';
    private int $otpMaxAttempts = 3;
    private int $otpDecaySeconds = 60;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
        $this->middleware('auth')->only('logout');
    }

    public function username()
    {
        //Check if the input is an email or mobile number
        $request = request();
        $input = $request->input('login');
        if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        return 'mobile';
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    protected function credentials(Request $request)
    {
        $credentials = [
            $this->username() => $request->input('login'),
            'password' => $request->input('password'),
        ];
        return $credentials;
    }

    public function requestOtp(Request $request)
    {
        $request->validate([
            'login' => ['required', 'string'],
        ]);

        $login = trim((string) $request->input('login'));
        $field = filter_var($login, FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';

        if ($field === 'mobile') {
            $digits = preg_replace('/\D+/', '', $login);

            if (!is_string($digits) || strlen($digits) < 10 || strlen($digits) > 15) {
                return back()->withErrors([
                    'login' => 'Please enter a valid email address or mobile number.',
                ])->withInput();
            }

            // Normalize Indian country code if provided as +91XXXXXXXXXX.
            if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
                $login = substr($digits, 2);
            } else {
                $login = $digits;
            }
        }

        if ($field === 'mobile' && !preg_match('/^[0-9]{10,15}$/', $login)) {
            return back()->withErrors([
                'login' => 'Please enter a valid email address or mobile number.',
            ])->withInput();
        }

        $user = User::where($field, $login)->first();
        if (!$user) {
            return back()->withErrors([
                'login' => 'No account found with this email or mobile number.',
            ])->withInput();
        }

        $issued = $this->issueOtp($request, $user, $field, $login);
        if (!$issued) {
            return redirect()->route('otp.form')->with('status', 'OTP sent successfully.');
        }

        return redirect()->route('otp.form')->with('status', 'OTP sent successfully.');
    }

    public function showOtpForm(Request $request)
    {
        if (!$request->session()->has('otp_login_value')) {
            return redirect()->route('login')->withErrors([
                'login' => 'Please enter your email or mobile number first.',
            ]);
        }

        return view('auth.otp-login', [
            'loginValue' => (string) $request->session()->get('otp_login_value'),
            'displayLoginValue' => $this->formatLoginForDisplay(
                (string) $request->session()->get('otp_login_field'),
                (string) $request->session()->get('otp_login_value')
            ),
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $storedOtpHash = (string) $request->session()->get('otp_code_hash', '');
        $expiresAt = (int) $request->session()->get('otp_expires_at', 0);
        $loginValue = (string) $request->session()->get('otp_login_value', '');
        $loginField = (string) $request->session()->get('otp_login_field', '');
        $userId = (int) $request->session()->get('otp_user_id', 0);

        if (!$storedOtpHash || !$loginValue || !$loginField || !$userId) {
            return redirect()->route('login')->withErrors([
                'login' => 'OTP session expired. Please try again.',
            ]);
        }

        if (now()->timestamp > $expiresAt) {
            $request->session()->forget(['otp_login_value', 'otp_login_field', 'otp_user_id', 'otp_code_hash', 'otp_expires_at']);
            return redirect()->route('login')->withErrors([
                'login' => 'OTP expired. Please request a new OTP.',
            ]);
        }

        if (!Hash::check((string) $request->input('otp'), $storedOtpHash)) {
            return back()->withErrors([
                'otp' => 'Invalid OTP. Please try again.',
            ])->withInput();
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['otp_login_value', 'otp_login_field', 'otp_user_id', 'otp_code_hash', 'otp_expires_at']);
            return redirect()->route('login')->withErrors([
                'login' => 'User not found. Please try again.',
            ]);
        }

        Auth::login($user);
        $request->session()->forget(['otp_login_value', 'otp_login_field', 'otp_user_id', 'otp_code_hash', 'otp_expires_at']);

        return redirect()->intended($this->redirectTo);
    }

    public function resendOtp(Request $request)
    {
        $loginValue = (string) $request->session()->get('otp_login_value', '');
        $loginField = (string) $request->session()->get('otp_login_field', '');
        $userId = (int) $request->session()->get('otp_user_id', 0);

        if (!$loginValue || !$loginField || !$userId) {
            return redirect()->route('login')->withErrors([
                'login' => 'OTP session expired. Please start again.',
            ]);
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['otp_login_value', 'otp_login_field', 'otp_user_id', 'otp_code_hash', 'otp_expires_at']);
            return redirect()->route('login')->withErrors([
                'login' => 'User not found. Please try again.',
            ]);
        }

        $issued = $this->issueOtp($request, $user, $loginField, $loginValue);
        if (!$issued) {
            return redirect()->route('otp.form')->with('status', 'New OTP sent successfully.');
        }

        return redirect()->route('otp.form')->with('status', 'New OTP sent successfully.');
    }

    private function formatLoginForDisplay(string $field, string $value): string
    {
        if ($field === 'email') {
            $parts = explode('@', $value);
            if (count($parts) !== 2) {
                return $value;
            }

            $name = $parts[0];
            $domain = $parts[1];
            $maskedName = strlen($name) <= 2
                ? str_repeat('*', max(strlen($name), 1))
                : substr($name, 0, 2) . str_repeat('*', max(strlen($name) - 2, 1));

            return $maskedName . '@' . $domain;
        }

        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (strlen($digits) === 10) {
            return 'IN +91 ' . substr($digits, 0, 3) . '***' . substr($digits, -4);
        }

        if (strlen($digits) > 6) {
            return '+' . substr($digits, 0, 2) . ' ' . str_repeat('*', max(strlen($digits) - 6, 1)) . substr($digits, -4);
        }

        return $value;
    }

    private function issueOtp(Request $request, User $user, string $field, string $login): bool
    {
        $limitKey = $this->otpRateLimitKey($request, $field, $login);
        if (RateLimiter::tooManyAttempts($limitKey, $this->otpMaxAttempts)) {
            return false;
        }

        RateLimiter::hit($limitKey, $this->otpDecaySeconds);

        $otp = (string) random_int(100000, 999999);

        $request->session()->put([
            'otp_login_value' => $login,
            'otp_login_field' => $field,
            'otp_user_id' => $user->id,
            'otp_code_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(5)->timestamp,
        ]);

        if ($field === 'email' && !empty($user->email)) {
            Mail::to($user->email)->queue(new OtpLoginMail($otp, 5));
        }

        return true;
    }

    private function otpRateLimitKey(Request $request, string $field, string $login): string
    {
        return 'otp-send:' . sha1($request->ip() . '|' . $field . '|' . strtolower($login));
    }
}
