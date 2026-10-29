@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
<div class="login-page">
    <div class="login-card">
        <div class="logo">
            <img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB logo">
            <h1>Reset your password</h1>
            <p>Enter your work email. We will send a secure reset link.</p>
        </div>

        @if(session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div style="margin-bottom:0.85rem">
                <label for="email">Work email</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus>
            </div>
            <button class="btn btn-primary" style="width:100%" type="submit">Send reset link</button>
        </form>

        <p style="margin-top:1rem;text-align:center">
            <a href="{{ route('login') }}">Back to sign in</a>
        </p>
        @unless(app()->environment('production'))
            <div class="demo-note">
                DEMO users (e.g. <code>submitter@nnmfb.demo</code>) can request resets.
                With <code>MAIL_MAILER=log</code>, the link appears in <code>storage/logs/laravel.log</code>.
                Production uses Zoho SMTP from <code>.env</code>.
            </div>
        @endunless
    </div>
</div>
@endsection
