<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketStatusHistory extends Model
{
    protected $fillable = [
        'ticket_id', 'changed_by', 'from_status', 'to_status', 'reason', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TransferTicket::class, 'ticket_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
