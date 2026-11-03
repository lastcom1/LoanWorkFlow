<?php

namespace App\Mail;

use App\Models\TransferTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketStatusUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TransferTicket $ticket,
        public User $recipient,
        public array $extra = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[NNMFB] Ticket Status Update — '.$this->ticket->ticket_ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-status-update',
            with: [
                'ticket' => $this->ticket,
                'recipient' => $this->recipient,
                'messageText' => $this->extra['message'] ?? 'Your ticket status has been updated.',
                'portalUrl' => url('/tickets/'.$this->ticket->id),
            ],
        );
    }
}
