<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuplicateMatchRecord extends Model
{
    protected $fillable = [
        'ticket_id', 'matched_ticket_id', 'match_type', 'match_key',
        'match_snapshot', 'was_overridden',
    ];

    protected function casts(): array
    {
        return [
            'match_snapshot' => 'array',
            'was_overridden' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TransferTicket::class, 'ticket_id');
    }

    public function matchedTicket(): BelongsTo
    {
        return $this->belongsTo(TransferTicket::class, 'matched_ticket_id');
    }
}
