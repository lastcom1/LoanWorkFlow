@extends('emails._layout')
@section('content')
<h2 style="margin:0 0 12px;font-size:20px;">Ticket Claimed / Assigned</h2>
<p>Hello {{ $recipient->name }},</p>
<p>Ticket <strong>{{ $ticket->ticket_ref }}</strong> has been claimed and is now under processing.</p>
<table cellpadding="6" style="font-size:14px;">
<tr><td><strong>Officer</strong></td><td>{{ $ticket->assignedOfficer?->name }}</td></tr>
<tr><td><strong>Account</strong></td><td>{{ $ticket->maskedAccount() }}</td></tr>
<tr><td><strong>Amount</strong></td><td>{{ $ticket->formattedAmount() }}</td></tr>
<tr><td><strong>Claimed at</strong></td><td>{{ optional($ticket->claimed_at)->toDayDateTimeString() }}</td></tr>
</table>
@endsection
