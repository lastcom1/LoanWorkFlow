@extends('layouts.guest')
@section('title', 'Requirements')
@section('content')
<div class="login-page">
<div class="login-card" style="width:min(640px,100%)">
<h1 style="font-family:var(--font-display)">Requirements</h1>
<ul>
@foreach($checks as $label => $ok)
<li style="color:{{ $ok ? '#1f7a4d' : '#a11d2e' }}">{{ $label }}: {{ $ok ? 'OK' : 'FAIL' }}</li>
@endforeach
</ul>
<h3>Structure</h3>
<ul>
@foreach($structure as $label => $ok)
<li style="color:{{ $ok ? '#1f7a4d' : '#a11d2e' }}">{{ $label }}: {{ $ok ? 'OK' : 'MISSING' }}</li>
@endforeach
</ul>
@php $ready = !in_array(false, $checks, true) && !in_array(false, $structure, true); @endphp
@if($ready)
<a class="btn btn-primary" href="{{ route('installer.env') }}">Continue</a>
@else
<div class="alert alert-danger">Resolve failed checks before continuing.</div>
@endif
</div>
</div>
@endsection
