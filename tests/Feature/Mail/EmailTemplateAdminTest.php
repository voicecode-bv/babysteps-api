<?php

use App\Filament\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Resources\EmailTemplates\Pages\ListEmailTemplates;
use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\TestEmailTemplateMail;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists every registered email template', function () {
    Livewire::test(ListEmailTemplates::class)
        ->assertCanSeeTableRecords(EmailTemplate::query()->get());
});

it('persists edits across all locales', function () {
    $template = EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->firstOrFail();

    Livewire::test(EditEmailTemplate::class, ['record' => $template->getKey()])
        ->fillForm([
            'subject_nl' => 'Aangepast NL onderwerp {inviter_name}',
            'body_nl' => 'Aangepaste NL body voor {inviter_name}.',
            'subject_en' => 'Updated EN subject',
            'body_en' => 'Updated EN body.',
            'subject_fr' => 'Sujet FR mis à jour',
            'body_fr' => 'Corps FR mis à jour.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $template->refresh();

    expect($template->subject_nl)->toBe('Aangepast NL onderwerp {inviter_name}')
        ->and($template->body_nl)->toBe('Aangepaste NL body voor {inviter_name}.')
        ->and($template->subject_fr)->toBe('Sujet FR mis à jour');
});

it('sends a test email via the sendTest action', function () {
    Mail::fake();

    $template = EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->firstOrFail();

    Livewire::test(EditEmailTemplate::class, ['record' => $template->getKey()])
        ->callAction('sendTest', data: [
            'email' => 'admin@example.test',
            'locale' => 'fr',
        ])
        ->assertHasNoActionErrors();

    Mail::assertSent(TestEmailTemplateMail::class, function (TestEmailTemplateMail $mail): bool {
        return $mail->hasTo('admin@example.test')
            && $mail->templateKey === EmailTemplateRegistry::CIRCLE_INVITATION
            && $mail->templateLocale === 'fr';
    });
});
