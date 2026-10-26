@extends('layouts.guest')
@section('title', 'Installer')
@section('content')
<div class="login-page">
<div class="login-card" style="width:min(560px,100%)">
<div class="logo">
<img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB">
<h1>Web Installer</h1>
<p>NNMFB Transfer &amp; Reversal Portal — shared hosting setup</p>
</div>
<p>This wizard configures database connectivity, Zoho SMTP environment values, runs migrations, and creates the initial administrator. It disables itself after a successful install.</p>
<a class="btn btn-primary" href="{{ route('installer.requirements') }}">Begin requirements check</a>
</div>
</div>
@endsection
