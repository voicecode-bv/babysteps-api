<?php

namespace App\Notifications;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\EmailTemplates\EmailTemplateRenderer;
use App\Models\CircleInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CircleInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CircleInvitation $invitation,
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
        $rendered = app(EmailTemplateRenderer::class)->render(
            EmailTemplateRegistry::CIRCLE_INVITATION,
            [
                'inviter_name' => $this->invitation->inviter->name,
            ],
        );

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->markdown('emails.templated', ['body' => $rendered['body']]);
    }
}
