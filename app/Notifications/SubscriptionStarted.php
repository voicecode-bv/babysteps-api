<?php

namespace App\Notifications;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\EmailTemplates\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;

class SubscriptionStarted extends Notification implements ShouldQueue
{
    use Queueable;

    private const DONATE_URLS = [
        'nl' => 'https://innerr.app/nl/doneren/',
        'en' => 'https://innerr.app/en/donate/',
        'fr' => 'https://innerr.app/fr/don/',
    ];

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = App::getLocale();

        $rendered = app(EmailTemplateRenderer::class)->render(
            EmailTemplateRegistry::SUBSCRIPTION_STARTED,
            [
                'recipient_name' => $notifiable->name,
                'donate_url' => self::DONATE_URLS[$locale] ?? self::DONATE_URLS['en'],
            ],
        );

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->markdown('emails.templated', ['body' => $rendered['body']]);
    }
}
