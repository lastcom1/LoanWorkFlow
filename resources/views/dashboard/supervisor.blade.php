@extends('layouts.app')

@section('title', 'Supervisor Dashboard')
@section('heading', 'Operations Oversight')
@section('subheading', 'Queue health, workloads, duplicates, and escalations')

@section('content')
<div class="stats">
    <div class="stat"><div class="label">Available</div><div class="value">{{ $stats['available'] }}</div></div>
    <div class="stat"><div class="label">In progress</div><div class="value">{{ $stats['in_progress'] }}</div></div>
    <div class="stat"><div class="label">Duplicate flagged</div><div class="value">{{ $stats['duplicates'] }}</div></div>
    <div class="stat"><div class="label">Rejected</div><div class="value">{{ $stats['rejected'] }}</div></div>
    <div class="stat"><div class="label">Completed</div><div class="value">{{ $stats['completed'] }}</div></div>
</div>

<div class="panel">
    <h2>FT Officer workloads</h2>
    <table class="data">
        <thead><tr><th>Officer</th><th>Email</th><th>Open tickets</th></tr></thead>
        <tbody>
        @foreach($workloads as $officer)
            <tr>
                <td>{{ $officer->name }}</td>
                <td>{{ $officer->email }}</td>
                <td>{{ $officer->open_tickets_count }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="panel">
    <h2>Recent tickets</h2>
    @include('tickets._table', ['tickets' => $recent])
</div>
@endsection
