@extends('layouts.guest')

@section('title', 'Change password')

@section('content')
<div class="login-page">
    <div class="login-card">
        <div class="logo">
            <img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB logo">
            <h1>Change your password</h1>
            <p>For security, you must set a new password before using the portal.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.change.update') }}">
            @csrf
            <div style="margin-bottom:0.85rem">
                <label for="current_password">Temporary / current password</label>
                <input id="current_password" type="password" name="current_password" required autofocus autocomplete="current-password">
            </div>
            <div style="margin-bottom:0.85rem">
                <label for="password">New password</label>
                <input id="password" type="password" name="password" required autocomplete="new-password">
            </div>
            <div style="margin-bottom:1rem">
                <label for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button class="btn btn-primary" style="width:100%" type="submit">Save new password</button>
        </form>

        <form method="POST" action="{{ route('logout') }}" style="margin-top:1rem;text-align:center">
            @csrf
            <button class="btn btn-outline btn-sm" type="submit">Sign out</button>
        </form>
    </div>
</div>
@endsection
