<?php

namespace App\Mail;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\EmailTemplates\EmailTemplateRenderer;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\App;

class TestEmailTemplateMail extends Mailable
{
    use Queueable;

    public function __construct(
        public string $templateKey,
        public string $templateLocale,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->renderForLocale()['subject']);
    }

    public function content(): Content
    {
        $rendered = $this->renderForLocale();

        if ($this->isRawHtml()) {
            return new Content(
                view: 'emails.templated-html',
                with: ['body' => $rendered['body']],
            );
        }

        return new Content(
            markdown: 'emails.templated',
            with: ['body' => $rendered['body']],
        );
    }

    /**
     * @return array{subject: string, body: string}
     */
    private function renderForLocale(): array
    {
        $samples = EmailTemplateRegistry::get($this->templateKey)['samples'] ?? [];

        $original = App::getLocale();

        try {
            App::setLocale($this->templateLocale);

            return app(EmailTemplateRenderer::class)->render(
                $this->templateKey,
                $samples,
                $this->templateLocale,
            );
        } finally {
            App::setLocale($original);
        }
    }

    private function isRawHtml(): bool
    {
        $template = EmailTemplate::query()->where('key', $this->templateKey)->first();

        if ($template instanceof EmailTemplate) {
            return $template->isRawHtml();
        }

        return (EmailTemplateRegistry::get($this->templateKey)['format'] ?? EmailTemplate::FORMAT_MARKDOWN_MESSAGE)
            === EmailTemplate::FORMAT_RAW_HTML;
    }
}
