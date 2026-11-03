@extends('layouts.app')

@section('title', 'FT Officer Dashboard')
@section('heading', 'Fund Transfer Officer Queue')
@section('subheading', 'Exclusive claim — only one officer can pick up each ticket')

@section('content')
<div class="stats">
    <div class="stat"><div class="label">Available for pickup</div><div class="value">{{ $queue->total() }}</div></div>
    <div class="stat"><div class="label">My open tickets</div><div class="value">{{ $mine->count() }}</div></div>
    <div class="stat"><div class="label">Completed today</div><div class="value">{{ $completedToday }}</div></div>
</div>

<div class="panel">
    <h2>My assigned tickets</h2>
    @if($mine->isEmpty())
        <p class="muted">No open assignments. Claim a ticket from the queue below.</p>
    @else
        <table class="data">
            <thead>
            <tr><th>Ref</th><th>Category</th><th>Account</th><th>Amount</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
            @foreach($mine as $ticket)
                <tr>
                    <td><a href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_ref }}</a></td>
                    <td>{{ $ticket->categoryLabel() }}</td>
                    <td>{{ $ticket->maskedAccount() }}</td>
                    <td>{{ $ticket->formattedAmount() }}</td>
                    <td><x-status-badge :status="$ticket->status" /></td>
                    <td><a class="btn btn-sm btn-outline" href="{{ route('tickets.show', $ticket) }}">Open</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="panel">
    <h2>Eligible queue</h2>
    <table class="data">
        <thead>
        <tr><th>Ref</th><th>Category</th><th>Branch</th><th>Account</th><th>Amount</th><th>Duplicate</th><th></th></tr>
        </thead>
        <tbody>
        @forelse($queue as $ticket)
            <tr>
                <td>{{ $ticket->ticket_ref }}</td>
                <td>{{ $ticket->categoryLabel() }}</td>
                <td>{{ $ticket->branch?->name }}</td>
                <td>{{ $ticket->maskedAccount() }}</td>
                <td>{{ $ticket->formattedAmount() }}</td>
                <td>@if($ticket->is_duplicate_flagged)<span class="badge badge-duplicate">Possible duplicate</span>@else — @endif</td>
                <td class="actions">
                    <a class="btn btn-sm btn-outline" href="{{ route('tickets.show', $ticket) }}">View</a>
                    <form method="POST" action="{{ route('tickets.claim', $ticket) }}">
                        @csrf
                        <button class="btn btn-sm btn-primary" type="submit">Pick Up</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">Queue is empty.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="margin-top:0.75rem">{{ $queue->links() }}</div>
</div>
@endsection
