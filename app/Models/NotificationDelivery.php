<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NotificationDelivery extends Model
{
    protected $fillable = [
        'uuid', 'ticket_id', 'user_id', 'channel', 'notification_type',
        'recipient_email', 'subject', 'status', 'attempts', 'error_message',
        'queued_at', 'sent_at', 'failed_at', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
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
