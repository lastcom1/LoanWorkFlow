@extends('layouts.guest')
@section('title', 'Environment')
@section('content')
<div class="login-page">
<div class="login-card" style="width:min(680px,100%)">
<h1 style="font-family:var(--font-display)">Database &amp; local mail</h1>
<p class="help">This server blocks outbound SMTP. Mail is sent with local sendmail. The From address must be <code>@nigerianavymfb.com</code> or Exim will reject it.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('installer.env.save') }}" class="form-grid">
@csrf
<div class="full"><label>APP URL</label><input type="url" name="app_url" value="{{ old('app_url', url('/')) }}" required></div>
<div><label>DB host</label><input name="db_host" value="{{ old('db_host', '127.0.0.1') }}" required></div>
<div><label>DB port</label><input name="db_port" value="{{ old('db_port', '3306') }}" required></div>
<div><label>DB name</label><input name="db_database" value="{{ old('db_database') }}" required></div>
<div><label>DB username</label><input name="db_username" value="{{ old('db_username') }}" required></div>
<div class="full"><label>DB password</label><input type="password" name="db_password" value="{{ old('db_password') }}"></div>
<div><label>From address</label><input type="email" name="mail_from_address" value="{{ old('mail_from_address', 'noreply@nigerianavymfb.com') }}" required></div>
<div><label>From name</label><input name="mail_from_name" value="{{ old('mail_from_name', 'NNMFB Transfer Portal') }}" required></div>
<div class="full"><button class="btn btn-primary" type="submit">Save &amp; test DB</button></div>
</form>
</div>
</div>
@endsection
