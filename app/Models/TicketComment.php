<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketComment extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'body', 'is_internal', 'is_info_request', 'is_info_response',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_info_request' => 'boolean',
            'is_info_response' => 'boolean',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(TransferTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
