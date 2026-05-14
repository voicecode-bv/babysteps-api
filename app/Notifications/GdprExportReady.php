<?php

namespace App\Notifications;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\EmailTemplates\EmailTemplateRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class GdprExportReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $path,
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
        $hours = (int) config('gdpr.export.expiry_hours');
        $url = Storage::temporaryUrl($this->path, now()->addHours($hours));

        $rendered = app(EmailTemplateRenderer::class)->render(
            EmailTemplateRegistry::GDPR_EXPORT_READY,
            [
                'recipient_name' => $notifiable->name,
                'download_url' => $url,
                'hours' => $hours,
            ],
        );

        return (new MailMessage)
            ->subject($rendered['subject'])
            ->markdown('emails.templated', ['body' => $rendered['body']]);
    }
}
