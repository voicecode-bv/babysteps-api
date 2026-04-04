<?php

namespace App\Notifications;

use App\Models\CircleInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CircleInvitationAcceptedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CircleInvitation $invitation,
        public string $acceptedByName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $circleName = $this->invitation->circle->name;

        return (new MailMessage)
            ->subject("{$this->acceptedByName} has joined {$circleName}")
            ->greeting('Good news!')
            ->line("{$this->acceptedByName} has accepted your invitation and joined the circle \"{$circleName}\".");
    }
}
