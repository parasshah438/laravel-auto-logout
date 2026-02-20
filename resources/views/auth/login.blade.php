@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Sign in') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <div class="row mb-3">
                            <label for="login" class="col-md-4 col-form-label text-md-end">{{ __('Enter mobile number or email') }}</label>

                            <div class="col-md-6">
                                <input id="login" type="text" class="form-control @error('login') is-invalid @enderror" name="login" value="{{ old('login') }}" required autocomplete="email" autofocus>

                                @error('login')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <label for="password" class="col-md-4 col-form-label text-md-end">{{ __('Password') }}</label>

                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required autocomplete="current-password">

                                @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 offset-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>

                                    <label class="form-check-label" for="remember">
                                        {{ __('Remember Me') }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="submit" class="btn btn-primary">
                                    {{ __('Sign in') }}
                                </button>

                                @if (Route::has('password.request'))
                                    <a class="btn btn-link" href="{{ route('password.request') }}">
                                        {{ __('Forgot Your Password?') }}
                                    </a>
                                @endif
                            </div>
                        </div>

                        <div class="row my-4">
                            <div class="col-md-8 offset-md-4">
                                <div class="d-flex align-items-center text-muted">
                                    <hr class="flex-grow-1 m-0">
                                    <span class="px-3 small fw-semibold text-uppercase">{{ __('OR') }}</span>
                                    <hr class="flex-grow-1 m-0">
                                </div>
                            </div>
                        </div>

                        <div class="row mb-0">
                            <div class="col-md-8 offset-md-4">
                                <button type="button" id="otp-login-btn" class="btn btn-outline-secondary w-100 py-2 fw-semibold">
                                    {{ __('Sign in with an OTP') }}
                                </button>
                            </div>
                        </div>

                    </form>

                    <form id="otp-request-form" method="POST" action="{{ route('otp.request') }}" class="d-none">
                        @csrf
                        <input type="hidden" name="login" id="otp-login-input">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const otpButton = document.getElementById('otp-login-btn');
    const loginInput = document.getElementById('login');
    const otpForm = document.getElementById('otp-request-form');
    const otpLoginInput = document.getElementById('otp-login-input');

    if (!otpButton || !loginInput || !otpForm || !otpLoginInput) {
        return;
    }

    otpButton.addEventListener('click', function () {
        const value = loginInput.value.trim();
        const isEmail = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        const isMobile = /^[0-9]{10,15}$/.test(value);

        if (!value || (!isEmail && !isMobile)) {
            loginInput.classList.add('is-invalid');
            let feedback = loginInput.parentElement.querySelector('.otp-login-feedback');
            if (!feedback) {
                feedback = document.createElement('span');
                feedback.className = 'invalid-feedback otp-login-feedback';
                feedback.setAttribute('role', 'alert');
                loginInput.parentElement.appendChild(feedback);
            }
            feedback.innerHTML = '<strong>Please enter a valid email or mobile number.</strong>';
            return;
        }

        loginInput.classList.remove('is-invalid');
        const feedback = loginInput.parentElement.querySelector('.otp-login-feedback');
        if (feedback) {
            feedback.remove();
        }

        otpLoginInput.value = value;
        otpForm.submit();
    });
});
</script>
@endsection
