@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0 fw-semibold">{{ __('OTP Verification') }}</h5>
                </div>

                <div class="card-body p-4">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif

                    <p class="text-muted mb-2">{{ __('We sent a 6-digit OTP to:') }}</p>
                    <div class="bg-light border rounded px-3 py-2 mb-2 d-flex align-items-center justify-content-between">
                        <span class="fw-semibold">{{ $displayLoginValue }}</span>
                        <a
                            href="#changeLoginCollapse"
                            class="small text-decoration-none fw-semibold"
                            data-bs-toggle="collapse"
                            role="button"
                            aria-expanded="{{ $errors->has('login') ? 'true' : 'false' }}"
                            aria-controls="changeLoginCollapse"
                        >
                            {{ __('Change') }}
                        </a>
                    </div>

                    <div class="collapse {{ $errors->has('login') ? 'show' : '' }} mb-4" id="changeLoginCollapse">
                        <div class="border rounded p-3">
                            <form method="POST" action="{{ route('otp.request') }}">
                                @csrf
                                <label for="change_login" class="form-label small fw-semibold mb-2">{{ __('Mobile number or email') }}</label>
                                <input
                                    id="change_login"
                                    type="text"
                                    name="login"
                                    value="{{ old('login', $loginValue) }}"
                                    class="form-control @error('login') is-invalid @enderror"
                                    required
                                >
                                @error('login')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                @enderror
                                <button type="submit" class="btn btn-outline-primary btn-sm mt-3">
                                    {{ __('Update and resend OTP') }}
                                </button>
                            </form>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('otp.verify') }}">
                        @csrf

                        <div class="mb-4">
                            <label for="otp" class="form-label fw-semibold">{{ __('Enter OTP') }}</label>
                            <input
                                id="otp"
                                type="text"
                                maxlength="6"
                                class="form-control form-control-lg text-center tracking-wide @error('otp') is-invalid @enderror"
                                name="otp"
                                value="{{ old('otp') }}"
                                placeholder="000000"
                                required
                                autofocus
                            >
                            @error('otp')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                            {{ __('Verify & Sign In') }}
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="small text-muted">{{ __("Didn't receive the OTP?") }}</span>
                        <form method="POST" action="{{ route('otp.resend') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-link p-0 ms-1 align-baseline fw-semibold text-decoration-none">
                                {{ __('Resend OTP') }}
                            </button>
                        </form>
                    </div>

                    <div class="text-center mt-3">
                        <a href="{{ route('login') }}" class="small text-decoration-none">{{ __('Use different email or mobile') }}</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
