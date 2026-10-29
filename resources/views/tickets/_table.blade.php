<table class="data">
    <thead>
    <tr>
        <th>Ref</th>
        <th>Category</th>
        <th>Branch</th>
        <th>Account</th>
        <th>Amount</th>
        <th>Status</th>
        <th>SLA</th>
        <th>Officer</th>
        <th></th>
    </tr>
    </thead>
    <tbody>
    @forelse($tickets as $ticket)
        <tr>
            <td>
                <a href="{{ route('tickets.show', $ticket) }}">{{ $ticket->ticket_ref }}</a>
                @if($ticket->is_duplicate_flagged)
                    <div><span class="badge badge-duplicate">Duplicate flagged</span></div>
                @endif
            </td>
            <td>{{ $ticket->categoryLabel() }}</td>
            <td>{{ $ticket->branch?->name }}</td>
            <td>{{ $ticket->maskedAccount() }}</td>
            <td>{{ $ticket->formattedAmount() }}</td>
            <td><x-status-badge :status="$ticket->status" /></td>
            <td><x-sla-badge :ticket="$ticket" /></td>
            <td>{{ $ticket->assignedOfficer?->name ?? '—' }}</td>
            <td><a class="btn btn-sm btn-outline" href="{{ route('tickets.show', $ticket) }}">View</a></td>
        </tr>
    @empty
        <tr><td colspan="9" class="muted">No tickets found.</td></tr>
    @endforelse
    </tbody>
</table>
@if(method_exists($tickets, 'links'))
    <div style="margin-top:0.75rem">{{ $tickets->links() }}</div>
@endif
