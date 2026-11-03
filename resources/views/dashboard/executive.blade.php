@extends('layouts.app')

@section('title', 'MD/CEO Dashboard')
@section('heading', 'Executive Overview')
@section('subheading', 'Accountability view — accounts are masked on this board')

@section('content')
<div class="stats">
    <div class="stat"><div class="label">Open cases</div><div class="value">{{ $stats['open'] }}</div></div>
    <div class="stat"><div class="label">Claimed today</div><div class="value">{{ $stats['claimed_today'] }}</div></div>
    <div class="stat"><div class="label">Completed today</div><div class="value">{{ $stats['completed_today'] }}</div></div>
    <div class="stat"><div class="label">Rejected</div><div class="value">{{ $stats['rejected'] }}</div></div>
    <div class="stat"><div class="label">Duplicate flagged</div><div class="value">{{ $stats['duplicates'] }}</div></div>
</div>

<div class="panel">
    <h2>Volume by category</h2>
    <p>Fund Transfer Instructions: <strong>{{ $byCategory['fund_transfer'] ?? 0 }}</strong></p>
    <p>Reversal Complaints: <strong>{{ $byCategory['reversal_complaint'] ?? 0 }}</strong></p>
</div>

<div class="panel">
    <h2>Recent claims</h2>
    <table class="data">
        <thead><tr><th>Ref</th><th>Officer</th><th>Branch</th><th>Account</th><th>Amount</th><th>Claimed</th></tr></thead>
        <tbody>
        @forelse($recentClaims as $ticket)
            <tr>
                <td><a href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_ref }}</a></td>
                <td>{{ $ticket->assignedOfficer?->name }}</td>
                <td>{{ $ticket->branch?->name }}</td>
                <td>{{ $ticket->maskedAccount() }}</td>
                <td>{{ $ticket->formattedAmount() }}</td>
                <td>{{ optional($ticket->claimed_at)->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">No claims yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
