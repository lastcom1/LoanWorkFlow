@extends('emails._layout')
@section('content')
@php
    $officer = $ticket->assignedOfficer;
    $isSubmitter = $recipient->id === $ticket->submitter_id;
@endphp
<h2 style="margin:0 0 12px;font-size:20px;">{{ $isSubmitter ? 'Your ticket was picked up' : 'Ticket claimed / assigned' }}</h2>
<p>Hello {{ $recipient->name }},</p>
@if($isSubmitter)
<p>Ticket <strong>{{ $ticket->ticket_ref }}</strong> has been picked up and is now being handled.</p>
@else
<p>Ticket <strong>{{ $ticket->ticket_ref }}</strong> has been claimed and is now under processing.</p>
@endif
<table cellpadding="6" style="font-size:14px;">
<tr><td><strong>Ticket ref</strong></td><td>{{ $ticket->ticket_ref }}</td></tr>
<tr><td><strong>Picked up by</strong></td><td>{{ $officer?->name ?: '—' }}</td></tr>
<tr><td><strong>Role</strong></td><td>{{ $officer?->roleLabel() ?: 'Fund Transfer Officer' }}</td></tr>
<tr><td><strong>Branch</strong></td><td>{{ $officer?->branch?->name ?: ($ticket->branch?->name ?: '—') }}</td></tr>
<tr><td><strong>Account</strong></td><td>{{ $ticket->maskedAccount() }}</td></tr>
<tr><td><strong>Amount</strong></td><td>{{ $ticket->formattedAmount() }}</td></tr>
<tr><td><strong>Picked up at</strong></td><td>{{ optional($ticket->claimed_at)->toDayDateTimeString() }}</td></tr>
@if($ticket->sla_due_at)
<tr><td><strong>SLA due</strong></td><td>{{ $ticket->sla_due_at->toDayDateTimeString() }} ({{ (int) $ticket->sla_minutes }} min target)</td></tr>
@endif
</table>
@endsection
