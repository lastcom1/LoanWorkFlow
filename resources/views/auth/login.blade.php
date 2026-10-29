@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<div class="login-page">
    <div class="login-card">
        <div class="logo">
            <img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB logo">
            <h1>Nigerian Navy Microfinance Bank</h1>
            <p>Transfer &amp; Reversal Complaint Portal</p>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div style="margin-bottom:0.85rem">
                <label for="email">Work email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <div style="margin-bottom:0.85rem">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" required>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:0.75rem;margin-bottom:1rem;flex-wrap:wrap">
                <label style="font-weight:500;display:flex;gap:0.45rem;align-items:center;margin:0">
                    <input type="checkbox" name="remember" value="1"> Remember this device
                </label>
                <a href="{{ route('password.request') }}">Forgot password?</a>
            </div>
            <button class="btn btn-primary" style="width:100%" type="submit">Sign in securely</button>
        </form>

        @unless(app()->environment('production'))
            <div class="demo-note">
                <strong>DEMO credentials</strong> are listed in the local install guide.
                Change all passwords before any production use. Never enter PINs, CVVs, or OTPs in this portal.
            </div>
        @endunless
    </div>
</div>
@endsection
