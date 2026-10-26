@extends('layouts.guest')
@section('title', 'Not found')
@section('content')
<div class="login-page"><div class="login-card" style="text-align:center">
<img src="{{ asset('images/nnmfb-logo.png') }}" alt="NNMFB" style="width:56px;height:56px">
<h1>Page not found</h1>
<a class="btn btn-primary" href="{{ url('/') }}">Return</a>
</div></div>
@endsection
