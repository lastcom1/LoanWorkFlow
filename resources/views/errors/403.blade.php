@extends('layouts.guest')
@section('title', 'Forbidden')
@section('content')
<div class="login-page"><div class="login-card" style="text-align:center">
<img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB" style="width:56px;height:56px">
<h1>Access denied</h1>
<p class="muted">You are not authorized to view this resource.</p>
<a class="btn btn-primary" href="{{ url('/') }}">Return</a>
</div></div>
@endsection
