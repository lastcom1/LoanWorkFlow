@extends('layouts.guest')
@section('title', 'Admin')
@section('content')
<div class="login-page">
<div class="login-card">
<h1 style="font-family:var(--font-display)">Create administrator</h1>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('installer.admin.save') }}">
@csrf
<label>Name</label><input name="name" required>
<label style="margin-top:.5rem">Email</label><input type="email" name="email" required>
<label style="margin-top:.5rem">Password</label><input type="password" name="password" required>
<label style="margin-top:.5rem">Confirm password</label><input type="password" name="password_confirmation" required>
<button class="btn btn-primary" style="margin-top:1rem;width:100%" type="submit">Finish install</button>
</form>
</div>
</div>
@endsection
