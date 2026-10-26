@extends('layouts.guest')
@section('title', 'Installed')
@section('content')
<div class="login-page">
<div class="login-card">
<img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB" style="width:64px;height:64px;display:block;margin:0 auto 1rem">
<h1 style="text-align:center;font-family:var(--font-display)">Installation complete</h1>
<p>The installer lock file has been written{{ $locked ? '' : ' (pending)' }}. Remove or protect <code>/install</code> routes in production and set <code>APP_DEBUG=false</code>.</p>
<a class="btn btn-primary" style="width:100%" href="{{ route('login') }}">Go to sign in</a>
</div>
</div>
@endsection
