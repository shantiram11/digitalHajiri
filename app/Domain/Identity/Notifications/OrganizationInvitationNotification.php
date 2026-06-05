<?php

declare(strict_types=1);

namespace App\Domain\Identity\Notifications;

use App\Domain\Identity\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OrganizationInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly OrganizationInvitation $invitation) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.url'), '/')
            . '/accept-invitation?token=' . urlencode($this->invitation->token);

        $orgName = $this->invitation->organization->name ?? 'an organization';
        $inviter = $this->invitation->invitedBy?->name ?? 'An administrator';

        return (new MailMessage)
            ->subject("You're invited to join {$orgName} on e-Hajiri")
            ->greeting('Namaste!')
            ->line("{$inviter} has invited you to join {$orgName} on e-Hajiri.")
            ->action('Accept invitation', $url)
            ->line('This invitation expires on ' . $this->invitation->expires_at->toFormattedDateString() . '.')
            ->line('If you did not expect this, you can ignore this email.');
    }
}
