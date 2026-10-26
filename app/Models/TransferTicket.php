<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransferTicket extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORY_FUND_TRANSFER = 'fund_transfer';
    public const CATEGORY_REVERSAL = 'reversal_complaint';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_INFO_REQUIRED = 'info_required';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'ticket_ref', 'category', 'status', 'priority',
        'submitter_id', 'branch_id', 'assigned_officer_id',
        'customer_account', 'customer_account_normalized',
        'amount', 'amount_normalized', 'transaction_at',
        'original_reference', 'original_reference_normalized',
        'source_channel', 'beneficiary_name', 'beneficiary_bank',
        'beneficiary_account', 'description', 'reversal_reason',
        'confirmation_accepted', 'is_duplicate_flagged',
        'duplicate_override', 'duplicate_override_reason', 'duplicate_override_by',
        'rejection_reason', 'rejected_by', 'rejected_at',
        'resolution_notes', 'processing_outcome',
        'claimed_at', 'started_at', 'completed_at', 'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_at' => 'datetime',
            'confirmation_accepted' => 'boolean',
            'is_duplicate_flagged' => 'boolean',
            'duplicate_override' => 'boolean',
            'rejected_at' => 'datetime',
            'claimed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitter_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignedOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_officer_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'ticket_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(TicketStatusHistory::class, 'ticket_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class, 'ticket_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class, 'ticket_id');
    }

    public function duplicateMatches(): HasMany
    {
        return $this->hasMany(DuplicateMatchRecord::class, 'ticket_id');
    }

    public function notificationDeliveries(): HasMany
    {
        return $this->hasMany(NotificationDelivery::class, 'ticket_id');
    }

    public function isAvailableForClaim(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_AVAILABLE], true)
            && $this->assigned_officer_id === null;
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_REJECTED], true);
    }

    public function categoryLabel(): string
    {
        return match ($this->category) {
            self::CATEGORY_FUND_TRANSFER => 'Fund Transfer Instruction',
            self::CATEGORY_REVERSAL => 'Reversal Complaint',
            default => $this->category,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_AVAILABLE => 'Available for Pickup',
            self::STATUS_CLAIMED => 'Claimed',
            self::STATUS_IN_PROGRESS => 'In Progress',
            self::STATUS_INFO_REQUIRED => 'Additional Information Required',
            self::STATUS_COMPLETED => 'Resolved / Completed',
            self::STATUS_REJECTED => 'Rejected',
            default => $this->status,
        };
    }

    public function maskedAccount(): string
    {
        $account = preg_replace('/\D+/', '', (string) $this->customer_account) ?: (string) $this->customer_account;
        $len = strlen($account);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', max(0, $len - 4)).substr($account, -4);
    }

    public function formattedAmount(): string
    {
        return 'NGN '.number_format((float) $this->amount, 2);
    }

    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_AVAILABLE])
            ->whereNull('assigned_officer_id');
    }

    public function scopeForOfficer($query, int $officerId)
    {
        return $query->where('assigned_officer_id', $officerId);
    }

    public function scopeForSubmitter($query, int $submitterId)
    {
        return $query->where('submitter_id', $submitterId);
    }
}
