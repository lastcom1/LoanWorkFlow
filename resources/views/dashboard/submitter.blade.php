@extends('layouts.app')

@section('title', 'My Dashboard')
@section('heading', 'My Tickets')
@section('subheading', 'Submit and track fund transfer instructions and reversal complaints')
@section('actions')
    <a class="btn btn-gold" href="{{ route('tickets.create') }}">New Ticket</a>
@endsection

@section('content')
<div class="stats">
    <div class="stat"><div class="label">Open / Available</div><div class="value">{{ ($counts['available'] ?? 0) + ($counts['submitted'] ?? 0) }}</div></div>
    <div class="stat"><div class="label">In Progress</div><div class="value">{{ ($counts['claimed'] ?? 0) + ($counts['in_progress'] ?? 0) + ($counts['info_required'] ?? 0) }}</div></div>
    <div class="stat"><div class="label">Completed</div><div class="value">{{ $counts['completed'] ?? 0 }}</div></div>
    <div class="stat"><div class="label">Rejected</div><div class="value">{{ $counts['rejected'] ?? 0 }}</div></div>
</div>

<div class="panel">
    <h2>Recent submissions</h2>
    @include('tickets._table', ['tickets' => $tickets])
</div>
@endsection
