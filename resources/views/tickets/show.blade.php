@extends('layouts.app')

@section('title', $ticket->ticket_ref)
@section('heading', $ticket->ticket_ref)
@section('subheading', $ticket->categoryLabel().' · '.$ticket->statusLabel())

@section('content')
@if($ticket->is_duplicate_flagged)
<div class="alert alert-warning">
    <strong>Duplicate warning:</strong>
    This ticket was flagged against existing case(s). Matching limitations apply — typos or alternate references may evade detection.
</div>
@endif

<div class="panel">
    <div class="form-grid">
        <div><strong>Status</strong><div><x-status-badge :status="$ticket->status" /></div></div>
        <div><strong>Priority</strong><div>{{ ucfirst($ticket->priority) }}</div></div>
        <div><strong>Branch</strong><div>{{ $ticket->branch?->name }}</div></div>
        <div><strong>Submitter</strong><div>{{ $ticket->submitter?->name }}</div></div>
        <div><strong>Customer account</strong><div>{{ $ticket->maskedAccount() }}</div></div>
        <div><strong>Amount</strong><div>{{ $ticket->formattedAmount() }}</div></div>
        <div><strong>Original reference</strong><div>{{ $ticket->original_reference ?: '—' }}</div></div>
        <div><strong>Assigned officer</strong><div>{{ $ticket->assignedOfficer?->name ?: 'Unassigned' }}</div></div>
        <div><strong>Transaction at</strong><div>{{ optional($ticket->transaction_at)->toDayDateTimeString() ?: '—' }}</div></div>
        <div><strong>Submitted at</strong><div>{{ optional($ticket->submitted_at)->toDayDateTimeString() ?: '—' }}</div></div>
        <div class="full"><strong>Description</strong><div>{{ $ticket->description ?: '—' }}</div></div>
        @if($ticket->reversal_reason)
            <div class="full"><strong>Reversal / failure reason</strong><div>{{ $ticket->reversal_reason }}</div></div>
        @endif
        @if($ticket->rejection_reason)
            <div class="full"><strong>Rejection reason</strong><div>{{ $ticket->rejection_reason }}</div></div>
        @endif
        @if($ticket->resolution_notes)
            <div class="full"><strong>Resolution notes</strong><div>{{ $ticket->resolution_notes }}</div></div>
            <div><strong>Processing outcome</strong><div>{{ $ticket->processing_outcome }}</div></div>
            <div class="full help">Completion records portal processing only — not proof of core-banking execution.</div>
        @endif
    </div>

    <div class="actions" style="margin-top:1rem">
        @if(auth()->user()->hasRole('ft_officer') && $ticket->isAvailableForClaim())
            <form method="POST" action="{{ route('tickets.claim', $ticket) }}">@csrf
                <button class="btn btn-primary" type="submit">Pick Up</button>
            </form>
        @endif

        @if(auth()->user()->hasRole('ft_officer') && $ticket->assigned_officer_id === auth()->id() && $ticket->isOpen())
            @if($ticket->status === 'claimed')
                <form method="POST" action="{{ route('tickets.start', $ticket) }}">@csrf
                    <button class="btn btn-gold" type="submit">Start Processing</button>
                </form>
            @endif
            <button class="btn btn-outline" type="button" onclick="document.getElementById('info-modal').classList.add('open')">Request Info</button>
            <button class="btn btn-outline" type="button" onclick="document.getElementById('complete-modal').classList.add('open')">Mark Completed</button>
            <button class="btn btn-danger" type="button" onclick="document.getElementById('reject-modal').classList.add('open')">Reject</button>
        @endif
    </div>
</div>

@if($ticket->duplicateMatches->isNotEmpty())
<div class="panel">
    <h2>Linked duplicate matches</h2>
    @foreach($ticket->duplicateMatches as $match)
        @php $m = $match->matchedTicket; @endphp
        @if($m)
            <div class="dup-box">
                <strong><a href="{{ route('tickets.show', $m) }}">{{ $m->ticket_ref }}</a></strong>
                — {{ $m->maskedAccount() }} · {{ $m->formattedAmount() }} · {{ $m->statusLabel() }}
                <div class="muted">{{ $m->branch?->name }} · Officer: {{ $m->assignedOfficer?->name ?: 'unassigned' }} · Match: {{ $match->match_type }}</div>
            </div>
        @endif
    @endforeach
</div>
@endif

<div class="panel">
    <h2>Attachments</h2>
    @forelse($ticket->attachments as $file)
        <div><a href="{{ route('attachments.download', $file) }}">{{ $file->original_name }}</a> <span class="muted">({{ number_format($file->size_bytes/1024,1) }} KB)</span></div>
    @empty
        <p class="muted">No attachments.</p>
    @endforelse
</div>

<div class="panel">
    <h2>Conversation</h2>
    <ul class="timeline">
        @foreach($ticket->comments as $comment)
            @if($comment->is_internal && ! $canSeeInternal)
                @continue
            @endif
            <li>
                <strong>{{ $comment->user?->name }}</strong>
                @if($comment->is_internal)<span class="badge badge-claimed">Internal</span>@endif
                @if($comment->is_info_request)<span class="badge badge-info_required">Info request</span>@endif
                @if($comment->is_info_response)<span class="badge badge-available">Info response</span>@endif
                <div class="muted">{{ $comment->created_at->toDayDateTimeString() }}</div>
                <div>{{ $comment->body }}</div>
            </li>
        @endforeach
    </ul>

    @if($ticket->isOpen())
        <form method="POST" action="{{ route('tickets.comment', $ticket) }}" style="margin-top:1rem">
            @csrf
            <label for="body">Add comment</label>
            <textarea name="body" id="body" rows="3" required></textarea>
            @if($canSeeInternal && ! auth()->user()->hasRole('submitter'))
                <label style="margin-top:0.5rem;display:flex;gap:0.4rem;align-items:center;font-weight:500">
                    <input type="checkbox" name="is_internal" value="1"> Internal note (hidden from submitter)
                </label>
            @endif
            <div style="margin-top:0.75rem"><button class="btn btn-outline" type="submit">Post</button></div>
        </form>

        @if(auth()->user()->hasRole('submitter') && $ticket->submitter_id === auth()->id() && $ticket->status === 'info_required')
            <form method="POST" action="{{ route('tickets.respond-info', $ticket) }}" style="margin-top:1rem">
                @csrf
                <label for="info_message">Respond to information request</label>
                <textarea name="message" id="info_message" rows="3" required></textarea>
                <div style="margin-top:0.75rem"><button class="btn btn-primary" type="submit">Send response</button></div>
            </form>
        @endif
    @endif
</div>

<div class="panel">
    <h2>Status history</h2>
    <ul class="timeline">
        @foreach($ticket->statusHistories as $hist)
            <li>
                <strong>{{ $hist->from_status ?: '—' }} → {{ $hist->to_status }}</strong>
                by {{ $hist->changer?->name ?: 'system' }}
                <div class="muted">{{ $hist->created_at->toDayDateTimeString() }}</div>
                @if($hist->reason)<div>{{ $hist->reason }}</div>@endif
            </li>
        @endforeach
    </ul>
</div>

@if(auth()->user()->hasAnyRole(['supervisor','admin','md_ceo']) && $ticket->isOpen())
<div class="panel">
    <h2>Reassign (authorized + audited)</h2>
    <form method="POST" action="{{ route('tickets.reassign', $ticket) }}" class="form-grid">
        @csrf
        <div>
            <label>FT Officer</label>
            <select name="officer_id" required>
                @foreach(\App\Models\User::role('ft_officer')->where('is_active', true)->orderBy('name')->get() as $officer)
                    <option value="{{ $officer->id }}">{{ $officer->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="full">
            <label>Reason</label>
            <textarea name="reason" rows="2" required></textarea>
        </div>
        <div><button class="btn btn-outline" type="submit">Reassign</button></div>
    </form>
</div>
@endif

{{-- Modals --}}
<div class="modal-backdrop" id="info-modal">
    <div class="modal">
        <h3>Request additional information</h3>
        <form method="POST" action="{{ route('tickets.request-info', $ticket) }}">
            @csrf
            <textarea name="message" rows="4" required placeholder="What information is needed?"></textarea>
            <div class="actions" style="margin-top:0.75rem">
                <button class="btn btn-primary" type="submit">Send request</button>
                <button class="btn btn-outline" type="button" onclick="document.getElementById('info-modal').classList.remove('open')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="complete-modal">
    <div class="modal">
        <h3>Mark resolved / completed</h3>
        <p class="help">Records portal processing outcome only — not core-banking proof.</p>
        <form method="POST" action="{{ route('tickets.complete', $ticket) }}">
            @csrf
            <label>Processing outcome</label>
            <input type="text" name="processing_outcome" required placeholder="e.g. Instruction processed / referred to core">
            <label style="margin-top:0.5rem">Resolution notes</label>
            <textarea name="resolution_notes" rows="4" required></textarea>
            <div class="actions" style="margin-top:0.75rem">
                <button class="btn btn-primary" type="submit">Complete</button>
                <button class="btn btn-outline" type="button" onclick="document.getElementById('complete-modal').classList.remove('open')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="reject-modal">
    <div class="modal">
        <h3>Reject ticket</h3>
        <p class="help">Rejection is permanent to the workflow (record retained). A reason is mandatory.</p>
        <form method="POST" action="{{ route('tickets.reject', $ticket) }}">
            @csrf
            <label>Rejection reason</label>
            <textarea name="rejection_reason" rows="4" required minlength="10"></textarea>
            <label style="margin-top:0.75rem;display:flex;gap:0.45rem;align-items:flex-start;font-weight:500">
                <input type="checkbox" name="confirm_reject" value="1" required>
                I confirm this rejection and understand the ticket will be searchable, not deleted.
            </label>
            <div class="actions" style="margin-top:0.75rem">
                <button class="btn btn-danger" type="submit">Confirm reject</button>
                <button class="btn btn-outline" type="button" onclick="document.getElementById('reject-modal').classList.remove('open')">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection
