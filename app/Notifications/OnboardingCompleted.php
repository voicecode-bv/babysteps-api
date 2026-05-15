<?php

namespace App\Notifications;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\EmailTemplates\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingCompleted extends Notification implements ShouldQueue
{
    use Queueable;

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
            EmailTemplateRegistry::ONBOARDING_COMPLETED,
            [
                'recipient_name' => $notifiable->name,
            ],
        );

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->markdown('emails.templated', ['body' => $rendered['body']]);
    }
}
