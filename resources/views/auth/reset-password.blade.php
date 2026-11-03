@extends('layouts.guest')

@section('title', 'Choose new password')

@section('content')
<div class="login-page">
    <div class="login-card">
        <div class="logo">
            <img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB logo">
            <h1>Choose a new password</h1>
            <p>Use a strong password you do not reuse elsewhere.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div style="margin-bottom:0.85rem">
                <label for="email">Work email</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required>
            </div>
            <div style="margin-bottom:0.85rem">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div style="margin-bottom:1rem">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary" style="width:100%" type="submit">Update password</button>
        </form>

        <p style="margin-top:1rem;text-align:center">
            <a href="{{ route('login') }}">Back to sign in</a>
        </p>
    </div>
</div>
@endsection
