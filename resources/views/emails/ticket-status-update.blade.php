@extends('emails._layout')
@section('content')
<h2 style="margin:0 0 12px;font-size:20px;">Ticket Status Update</h2>
<p>Hello {{ $recipient->name }},</p>
<p>{{ $messageText }}</p>
<table cellpadding="6" style="font-size:14px;">
<tr><td><strong>Ticket</strong></td><td>{{ $ticket->ticket_ref }}</td></tr>
<tr><td><strong>Status</strong></td><td>{{ $ticket->statusLabel() }}</td></tr>
<tr><td><strong>Account</strong></td><td>{{ $ticket->maskedAccount() }}</td></tr>
<tr><td><strong>Amount</strong></td><td>{{ $ticket->formattedAmount() }}</td></tr>
<tr><td><strong>Updated</strong></td><td>{{ now()->toDayDateTimeString() }}</td></tr>
</table>
@endsection
