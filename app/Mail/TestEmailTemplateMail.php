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
    ) {
        if ($mailer = EmailTemplateRegistry::get($this->templateKey)['mailer'] ?? null) {
            $this->mailer($mailer);
        }
    }

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
                text: 'emails.templated-html-text',
                with: [
                    'body' => $rendered['body'],
                    'text' => $this->htmlToPlainText($rendered['body']),
                ],
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

    private function htmlToPlainText(string $html): string
    {
        $html = preg_replace('#<(script|style|head)[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</(p|h[1-6]|li|div|tr)>#i', "\n\n", $html) ?? $html;

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n[ \t]+/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
