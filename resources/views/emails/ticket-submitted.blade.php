@extends('emails._layout')
@section('content')
<h2 style="margin:0 0 12px;font-size:20px;">New Ticket Submitted</h2>
<p>Hello {{ $recipient->name }},</p>
<p>A new ticket has been submitted to the NNMFB Transfer &amp; Reversal portal.</p>
<table cellpadding="6" style="font-size:14px;">
<tr><td><strong>Ticket</strong></td><td>{{ $ticket->ticket_ref }}</td></tr>
<tr><td><strong>Category</strong></td><td>{{ $ticket->categoryLabel() }}</td></tr>
<tr><td><strong>Account</strong></td><td>{{ $ticket->maskedAccount() }}</td></tr>
<tr><td><strong>Amount</strong></td><td>{{ $ticket->formattedAmount() }}</td></tr>
<tr><td><strong>Submitted</strong></td><td>{{ optional($ticket->submitted_at)->toDayDateTimeString() }}</td></tr>
</table>
@endsection
