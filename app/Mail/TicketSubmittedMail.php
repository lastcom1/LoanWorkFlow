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

class TicketSubmittedMail extends Mailable implements ShouldQueue
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
            subject: '[NNMFB] New Ticket Submitted — '.$this->ticket->ticket_ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-submitted',
            with: [
                'ticket' => $this->ticket,
                'recipient' => $this->recipient,
                'portalUrl' => url('/tickets/'.$this->ticket->id),
            ],
        );
    }
}
