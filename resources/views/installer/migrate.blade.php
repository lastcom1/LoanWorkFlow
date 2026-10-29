@extends('layouts.guest')
@section('title', 'Migrate')
@section('content')
<div class="login-page">
<div class="login-card">
<h1 style="font-family:var(--font-display)">Run migrations</h1>
<p>This creates all application tables and seeds roles/branches. If CLI is unavailable on your host, use Package B deploy docs for manual SQL alternatives.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('installer.migrate.run') }}">@csrf
<button class="btn btn-primary" type="submit">Run migrations</button>
</form>
</div>
</div>
@endsection
