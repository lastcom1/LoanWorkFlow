<?php

namespace App\Services;

use App\Mail\TicketClaimedMail;
use App\Mail\TicketStatusUpdateMail;
use App\Mail\TicketSubmittedMail;
use App\Models\NotificationDelivery;
use App\Models\TransferTicket;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TicketNotificationService
{
    public function __construct(private AuditService $audit) {}

    public function ticketSubmitted(TransferTicket $ticket): void
    {
        $recipients = collect([$ticket->submitter])
            ->merge(User::role('supervisor')->where('is_active', true)->get())
            ->filter()
            ->unique('id');

        foreach ($recipients as $user) {
            $this->queueMail(
                $ticket,
                $user,
                TicketSubmittedMail::class,
                'ticket_submitted',
                "New ticket submitted: {$ticket->ticket_ref}"
            );
        }
    }

    public function ticketClaimed(TransferTicket $ticket): void
    {
        $ticket->loadMissing(['assignedOfficer.roles', 'assignedOfficer.branch', 'submitter', 'branch']);

        $recipients = collect([$ticket->submitter]);

        if (\App\Models\Setting::getValue('notify_supervisor_on_claim', '1') === '1') {
            $recipients = $recipients->merge(User::role('supervisor')->where('is_active', true)->get());
        }

        $recipients = $recipients
            ->merge(User::role('md_ceo')->where('is_active', true)->get())
            ->filter()
            ->unique('id');

        $officer = $ticket->assignedOfficer;
        $extra = [
            'picker_name' => $officer?->name,
            'picker_role' => $officer?->roleLabel(),
            'picker_branch' => $officer?->branch?->name,
            'ticket_ref' => $ticket->ticket_ref,
        ];

        foreach ($recipients as $user) {
            $subject = $user->id === $ticket->submitter_id
                ? "Ticket picked up: {$ticket->ticket_ref}"
                : "Ticket claimed: {$ticket->ticket_ref}";

            $this->queueMail(
                $ticket,
                $user,
                TicketClaimedMail::class,
                'ticket_claimed',
                $subject,
                $extra
            );
        }
    }

    public function ticketStatusUpdated(TransferTicket $ticket, string $message): void
    {
        $recipients = collect([$ticket->submitter, $ticket->assignedOfficer])
            ->filter()
            ->unique('id');

        foreach ($recipients as $user) {
            $this->queueMail(
                $ticket,
                $user,
                TicketStatusUpdateMail::class,
                'ticket_status_update',
                "Ticket update: {$ticket->ticket_ref}",
                ['message' => $message]
            );
        }
    }

    /**
     * @param  class-string  $mailableClass
     */
    private function queueMail(
        TransferTicket $ticket,
        User $user,
        string $mailableClass,
        string $type,
        string $subject,
        array $extra = []
    ): void {
        $delivery = NotificationDelivery::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'channel' => 'mail',
            'notification_type' => $type,
            'recipient_email' => $user->email,
            'subject' => $subject,
            'status' => 'queued',
            'attempts' => 0,
            'queued_at' => now(),
            'meta' => $extra,
        ]);

        try {
            $mailable = new $mailableClass($ticket, $user, $extra);
            Mail::to($user->email)->queue($mailable);

            $delivery->forceFill([
                'attempts' => $delivery->attempts + 1,
                'status' => 'sent',
                'sent_at' => now(),
            ])->save();

            $this->audit->log('notification_queued', $user, $delivery, $ticket, null, [
                'type' => $type,
                'recipient' => $user->email,
            ]);
        } catch (\Throwable $e) {
            $delivery->forceFill([
                'attempts' => $delivery->attempts + 1,
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $e->getMessage(),
            ])->save();

            Log::warning('Notification delivery failed', [
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);

            $this->audit->log('notification_failed', $user, $delivery, $ticket, null, null, [
                'error' => $e->getMessage(),
                'type' => $type,
            ]);
        }
    }
}
