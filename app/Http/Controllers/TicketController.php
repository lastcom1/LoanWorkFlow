<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\TicketAttachment;
use App\Models\TransferTicket;
use App\Models\User;
use App\Services\DuplicateDetectionService;
use App\Services\TicketClaimService;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;

class TicketController extends Controller
{
    public function __construct(
        private TicketService $tickets,
        private TicketClaimService $claims,
        private DuplicateDetectionService $duplicates,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = TransferTicket::query()->with(['branch', 'submitter', 'assignedOfficer']);

        if ($user->hasRole('submitter')) {
            $query->forSubmitter($user->id);
        } elseif ($user->hasRole('ft_officer')) {
            $query->where(function ($q) use ($user) {
                $q->available()->orWhere('assigned_officer_id', $user->id);
            });
        } elseif ($user->hasRole('auditor')) {
            // read-all
        } elseif (! $user->hasAnyRole(['admin', 'supervisor', 'md_ceo'])) {
            abort(403);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('ticket_ref', 'like', $q)
                    ->orWhere('original_reference', 'like', $q)
                    ->orWhere('customer_account', 'like', $q);
            });
        }

        $tickets = $query->latest()->paginate(20)->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function create(Request $request)
    {
        $this->authorizeSubmitter($request);
        $branches = Branch::query()->where('is_active', true)->orderBy('name')->get();

        return view('tickets.create', compact('branches'));
    }

    public function checkDuplicates(Request $request)
    {
        $this->authorizeSubmitter($request);

        $data = $request->validate([
            'category' => ['required', Rule::in([
                TransferTicket::CATEGORY_FUND_TRANSFER,
                TransferTicket::CATEGORY_REVERSAL,
            ])],
            'customer_account' => ['required', 'string', 'max:32'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'original_reference' => ['nullable', 'string', 'max:100'],
        ]);

        $accountNorm = $this->duplicates->normalizeAccount($data['customer_account']);
        $amountNorm = $this->duplicates->normalizeAmount($data['amount']);
        $refNorm = $this->duplicates->normalizeReference($data['original_reference'] ?? null);

        $matches = $this->duplicates->findMatches(
            $data['category'],
            $accountNorm,
            $amountNorm,
            $refNorm
        );

        // Submitters get a minimal payload (no other staff names, officer, branch, or deep links).
        $isPrivileged = $request->user()->hasAnyRole(['admin', 'supervisor', 'md_ceo', 'auditor', 'ft_officer']);

        return response()->json([
            'has_duplicates' => $matches->isNotEmpty(),
            'matches' => $matches->map(function (TransferTicket $t) use ($isPrivileged) {
                $row = [
                    'ticket_ref' => $t->ticket_ref,
                    'masked_account' => $t->maskedAccount(),
                    'amount' => $t->formattedAmount(),
                    'status' => $t->statusLabel(),
                    'is_open' => $t->isOpen(),
                    'submitted_at' => optional($t->submitted_at)->toDayDateTimeString(),
                ];

                if ($isPrivileged) {
                    $row['id'] = $t->id;
                    $row['branch'] = $t->branch?->name;
                    $row['submitter'] = $t->submitter?->name;
                    $row['assigned_officer'] = $t->assignedOfficer?->name;
                    $row['url'] = route('tickets.show', $t);
                }

                return $row;
            }),
            'message' => $matches->isNotEmpty()
                ? 'A similar ticket may already exist. Please review before submitting. Matching is based on '
                    .($data['category'] === TransferTicket::CATEGORY_REVERSAL
                        ? 'original/transaction reference number.'
                        : 'normalized customer account number and amount.')
                    .' Limitations: typos, different channels, or partial references may evade detection.'
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeSubmitter($request);

        $data = $request->validate([
            'category' => ['required', Rule::in([
                TransferTicket::CATEGORY_FUND_TRANSFER,
                TransferTicket::CATEGORY_REVERSAL,
            ])],
            'branch_id' => ['required', 'exists:branches,id'],
            'customer_account' => ['required', 'string', 'max:32', 'regex:/^[0-9\-\s]+$/'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'transaction_at' => ['nullable', 'date'],
            'original_reference' => [
                Rule::requiredIf($request->input('category') === TransferTicket::CATEGORY_REVERSAL),
                'nullable', 'string', 'max:100',
            ],
            'source_channel' => ['nullable', 'string', 'max:60'],
            'beneficiary_name' => ['nullable', 'string', 'max:255'],
            'beneficiary_bank' => ['nullable', 'string', 'max:255'],
            'beneficiary_account' => ['nullable', 'string', 'max:32'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reversal_reason' => [
                Rule::requiredIf($request->input('category') === TransferTicket::CATEGORY_REVERSAL),
                'nullable', 'string', 'max:5000',
            ],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'confirmation_accepted' => ['accepted'],
            'acknowledge_duplicate' => ['nullable', 'boolean'],
            'duplicate_override_reason' => ['nullable', 'string', 'max:1000'],
            'attachments.*' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        // Never collect secrets
        foreach (['pin', 'cvv', 'otp', 'password'] as $forbidden) {
            if ($request->filled($forbidden)) {
                abort(422, 'Sensitive credentials must not be submitted.');
            }
        }

        try {
            $ticket = $this->tickets->create(
                $request->user(),
                $data,
                $request->file('attachments', []) ?? []
            );
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['duplicate' => $e->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_ref} created successfully.");
    }

    public function show(Request $request, TransferTicket $ticket)
    {
        $this->authorizeView($request, $ticket);

        $ticket->load([
            'branch', 'submitter', 'assignedOfficer',
            'comments.user', 'attachments', 'statusHistories.changer',
            'assignments.officer', 'duplicateMatches.matchedTicket.branch',
            'duplicateMatches.matchedTicket.assignedOfficer',
        ]);

        $canSeeInternal = $request->user()->hasAnyRole(['admin', 'supervisor', 'ft_officer', 'md_ceo', 'auditor']);

        return view('tickets.show', compact('ticket', 'canSeeInternal'));
    }

    public function claim(Request $request, TransferTicket $ticket)
    {
        if (! $request->user()->hasRole('ft_officer')) {
            abort(403);
        }

        try {
            $claimed = $this->claims->claim($ticket, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['claim' => $e->getMessage()]);
        }

        return redirect()
            ->route('tickets.show', $claimed)
            ->with('success', "You have claimed ticket {$claimed->ticket_ref}.");
    }

    public function start(Request $request, TransferTicket $ticket)
    {
        $this->authorizeOfficerAction($request, $ticket);

        $ticket = $this->tickets->transition(
            $ticket,
            $request->user(),
            TransferTicket::STATUS_IN_PROGRESS,
            'Processing started'
        );

        return back()->with('success', 'Processing started.');
    }

    public function requestInfo(Request $request, TransferTicket $ticket)
    {
        $this->authorizeOfficerAction($request, $ticket);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->tickets->addComment($ticket, $request->user(), $data['message'], false, true, false);
        $this->tickets->transition(
            $ticket,
            $request->user(),
            TransferTicket::STATUS_INFO_REQUIRED,
            $data['message']
        );

        return back()->with('success', 'Additional information requested.');
    }

    public function respondInfo(Request $request, TransferTicket $ticket)
    {
        $user = $request->user();
        if (! $user->hasRole('submitter') || $ticket->submitter_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $this->tickets->addComment($ticket, $user, $data['message'], false, false, true);

        if ($ticket->status === TransferTicket::STATUS_INFO_REQUIRED && $ticket->assigned_officer_id) {
            $this->tickets->transition(
                $ticket,
                $user,
                TransferTicket::STATUS_IN_PROGRESS,
                'Submitter provided additional information'
            );
        }

        return back()->with('success', 'Response submitted.');
    }

    public function complete(Request $request, TransferTicket $ticket)
    {
        $this->authorizeOfficerAction($request, $ticket);

        $data = $request->validate([
            'processing_outcome' => ['required', 'string', 'max:80'],
            'resolution_notes' => ['required', 'string', 'max:5000'],
        ]);

        try {
            $this->tickets->transition(
                $ticket,
                $request->user(),
                TransferTicket::STATUS_COMPLETED,
                $data['resolution_notes'],
                [
                    'processing_outcome' => $data['processing_outcome'],
                    'resolution_notes' => $data['resolution_notes'],
                ]
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['processing_outcome' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticket marked completed. Note: completion records portal processing outcome only — it is not proof of core-banking execution.');
    }

    public function reject(Request $request, TransferTicket $ticket)
    {
        $this->authorizeOfficerAction($request, $ticket);

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:10', 'max:5000'],
            'confirm_reject' => ['accepted'],
        ]);

        try {
            $this->tickets->transition(
                $ticket,
                $request->user(),
                TransferTicket::STATUS_REJECTED,
                $data['rejection_reason']
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['rejection_reason' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticket rejected. The record is retained for search and audit.');
    }

    public function comment(Request $request, TransferTicket $ticket)
    {
        $this->authorizeView($request, $ticket);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
        ]);

        $internal = (bool) ($data['is_internal'] ?? false);
        if ($internal && $request->user()->hasRole('submitter')) {
            abort(403);
        }

        try {
            $this->tickets->addComment($ticket, $request->user(), $data['body'], $internal);
        } catch (RuntimeException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        return back()->with('success', 'Comment added.');
    }

    public function downloadAttachment(Request $request, TicketAttachment $attachment)
    {
        $ticket = $attachment->ticket;
        $this->authorizeView($request, $ticket);

        if (! Storage::disk('local')->exists($attachment->stored_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $attachment->stored_path,
            $attachment->original_name
        );
    }

    public function reassign(Request $request, TransferTicket $ticket)
    {
        if (! $request->user()->hasAnyRole(['supervisor', 'admin', 'md_ceo'])) {
            abort(403);
        }

        $data = $request->validate([
            'officer_id' => ['required', 'exists:users,id'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $officer = User::query()->findOrFail($data['officer_id']);
        if (! $officer->hasRole('ft_officer') || ! $officer->is_active) {
            return back()->withErrors(['officer_id' => 'Selected user is not an active FT Officer.']);
        }

        try {
            $this->tickets->reassign($ticket, $request->user(), $officer, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['officer_id' => $e->getMessage()]);
        }

        return back()->with('success', 'Ticket reassigned.');
    }

    private function authorizeSubmitter(Request $request): void
    {
        if (! $request->user()->hasAnyRole(['submitter', 'admin'])) {
            abort(403);
        }
    }

    private function authorizeView(Request $request, TransferTicket $ticket): void
    {
        $user = $request->user();

        if ($user->hasAnyRole(['admin', 'supervisor', 'md_ceo', 'auditor'])) {
            return;
        }
        if ($user->hasRole('submitter') && $ticket->submitter_id === $user->id) {
            return;
        }
        if ($user->hasRole('ft_officer') && (
            $ticket->assigned_officer_id === $user->id || $ticket->isAvailableForClaim()
        )) {
            return;
        }

        abort(403);
    }

    private function authorizeOfficerAction(Request $request, TransferTicket $ticket): void
    {
        $user = $request->user();
        if (! $user->hasRole('ft_officer') || $ticket->assigned_officer_id !== $user->id) {
            abort(403);
        }
        if (in_array($ticket->status, [TransferTicket::STATUS_COMPLETED, TransferTicket::STATUS_REJECTED], true)) {
            abort(422, 'Ticket is already closed.');
        }
    }
}
