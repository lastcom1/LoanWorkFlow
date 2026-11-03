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

class TicketClaimedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TransferTicket $ticket,
        public User $recipient,
        public array $extra = [],
    ) {}

    public function envelope(): Envelope
    {
        $forSubmitter = $this->recipient->id === $this->ticket->submitter_id;

        return new Envelope(
            subject: ($forSubmitter ? '[NNMFB] Ticket Picked Up — ' : '[NNMFB] Ticket Claimed — ').$this->ticket->ticket_ref,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.ticket-claimed',
            with: [
                'ticket' => $this->ticket,
                'recipient' => $this->recipient,
                'portalUrl' => url('/tickets/'.$this->ticket->id),
            ],
        );
    }
}
