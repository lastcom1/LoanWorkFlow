@extends('layouts.app')
@section('title', 'Audit trail')
@section('heading', 'Immutable audit trail')
@section('subheading', 'Append-only through the application UI — secrets are redacted')
@section('actions')
@if(auth()->user()->hasAnyRole(['admin','auditor']))
<a class="btn btn-outline" href="{{ route('auditor.audit-logs.export') }}">Export CSV</a>
@endif
@endsection
@section('content')
<div class="panel">
<form method="GET" class="form-grid" style="margin-bottom:1rem">
<div><label>Action</label><input name="action" value="{{ request('action') }}"></div>
<div><label>Ticket ref</label><input name="ticket_ref" value="{{ request('ticket_ref') }}"></div>
<div style="display:flex;align-items:end"><button class="btn btn-primary" type="submit">Filter</button></div>
</form>
<table class="data">
<thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Ticket</th><th>IP</th></tr></thead>
<tbody>
@foreach($logs as $log)
<tr>
<td>{{ optional($log->created_at)->toDayDateTimeString() }}</td>
<td>{{ $log->actor?->email ?: 'system' }}</td>
<td>{{ $log->action }}</td>
<td>{{ $log->ticket?->ticket_ref ?: '—' }}</td>
<td>{{ $log->ip_address }}</td>
</tr>
@endforeach
</tbody>
</table>
{{ $logs->links() }}
</div>
@endsection
