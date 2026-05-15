<?php

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\TestEmailTemplateMail;
use App\Models\Circle;
use App\Models\CircleInvitation;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Notifications\CircleInvitationAcceptedNotification;
use App\Notifications\CircleInvitationNotification;
use App\Notifications\GdprExportReady;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('mail.default', 'array');
    App::setLocale('en');
});

it('renders mail notifications in the recipient preferred locale', function () {
    $user = User::factory()->create(['locale' => 'nl']);

    config()->set('gdpr.export.directory', 'gdpr-exports');
    Storage::fake();
    Storage::put('gdpr-exports/1/fake.zip', 'x');

    $user->notify(new GdprExportReady('gdpr-exports/1/fake.zip'));

    $messages = app('mailer')->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1);

    $email = $messages[0]->getOriginalMessage();
    $body = $email->getHtmlBody();

    expect($email->getSubject())->toBe('Je data-export staat klaar')
        ->and($body)->toContain('Hallo '.$user->name)
        ->and($body)->toContain('Je persoonlijke data-export staat klaar om te downloaden.')
        ->and($body)->toContain('Download je gegevens')
        ->and($body)->toContain('Groetjes,')
        ->and($body)->toContain('Nicky van Innerr')
        ->and($body)->toContain('mailing.innerr.app/images/nicky.jpg');
});

it('renders the mail in English for recipients whose locale is en', function () {
    App::setLocale('nl');
    $user = User::factory()->create(['locale' => 'en']);

    $inviter = User::factory()->create();
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $user->id,
        'inviter_id' => $inviter->id,
    ]);

    $user->notify(new CircleInvitationNotification($invitation));

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();

    expect($email->getSubject())->toContain('has invited you')
        ->and($email->getHtmlBody())->toContain('Hello!');
});

it('sends raw_html templates without the mail message wrapper, with a plain-text alternative, and via the default transactional mailer', function () {
    EmailTemplate::query()->where('key', EmailTemplateRegistry::EARLY_ADOPTERS)->update([
        'body_format' => EmailTemplate::FORMAT_RAW_HTML,
        'subject_nl' => 'Welkom bij Innerr',
        'body_nl' => '<!doctype html><html><body><h1>Welkom!</h1><p>Hallo early adopter.</p><div class="pc-project-body">Tot snel.</div></body></html>',
    ]);

    $mailable = new TestEmailTemplateMail(
        templateKey: EmailTemplateRegistry::EARLY_ADOPTERS,
        templateLocale: 'nl',
    );

    expect($mailable->mailer)->toBeNull();

    Mail::to('jordan@example.test')->send($mailable);

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();

    expect($email->getSubject())->toBe('Welkom bij Innerr')
        ->and($email->getHtmlBody())->toContain('pc-project-body')
        ->and($email->getHtmlBody())->not->toContain('Groetjes,')
        ->and($email->getHtmlBody())->not->toContain('innerr-logo.png')
        ->and($email->getTextBody())->toContain('Welkom!')
        ->and($email->getTextBody())->toContain('Hallo early adopter.');
});

it('renders the early adopters mail as a markdown message with branded wrapper and signature', function () {
    $mailable = new TestEmailTemplateMail(
        templateKey: EmailTemplateRegistry::EARLY_ADOPTERS,
        templateLocale: 'nl',
    );

    Mail::to('jordan@example.test')->send($mailable);

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
    $body = $email->getHtmlBody();

    expect($email->getSubject())->toBe('Welkom bij Innerr — early access')
        ->and($body)->toContain('We hebben goed nieuws!')
        ->and($body)->toContain('Wat maakt Innerr anders?')
        ->and($body)->toContain('https://apps.apple.com/nl/app/innerr-priv')
        ->and($body)->toContain('play.google.com/store/apps/details?id=com.innerr.app')
        ->and($body)->toContain('Groetjes,')
        ->and($body)->toContain('Nicky van Innerr');
});

it('sets a Reply-To header on outgoing mail when configured', function () {
    config()->set('mail.reply_to.address', 'hello@innerr.test');
    config()->set('mail.reply_to.name', 'Innerr Support');

    app('mail.manager')->forgetMailers();
    Mail::alwaysReplyTo('hello@innerr.test', 'Innerr Support');

    EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->update([
        'subject_en' => 'Hi',
        'body_en' => 'Hello world.',
    ]);

    Mail::to('jordan@example.test')->send(new TestEmailTemplateMail(
        templateKey: EmailTemplateRegistry::CIRCLE_INVITATION,
        templateLocale: 'en',
    ));

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
    $replyTo = $email->getReplyTo()[0] ?? null;

    expect($replyTo?->getAddress())->toBe('hello@innerr.test');
});

it('compiles the mail button component when included in a template body', function () {
    EmailTemplate::query()
        ->where('key', EmailTemplateRegistry::GDPR_EXPORT_READY)
        ->update([
            'body_en' => "# Hi\n\nYour export is ready.\n\n<x-mail::button url=\"{download_url}\">Download your data</x-mail::button>",
        ]);

    config()->set('gdpr.export.directory', 'gdpr-exports');
    Storage::fake();
    Storage::put('gdpr-exports/1/fake.zip', 'x');

    $user = User::factory()->create(['locale' => 'en']);
    $user->notify(new GdprExportReady('gdpr-exports/1/fake.zip'));

    $body = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->getHtmlBody();

    expect($body)
        ->toContain('class="button button-primary"')
        ->toContain('Download your data')
        ->not->toContain('x-mail::button');
});

it('localizes the circle invitation accepted mail to the recipient locale', function () {
    $inviter = User::factory()->create(['locale' => 'nl']);
    $invitee = User::factory()->create(['name' => 'Alice']);
    $circle = Circle::factory()->for($inviter)->create(['name' => 'Family']);
    $invitation = CircleInvitation::factory()->create([
        'user_id' => $invitee->id,
        'inviter_id' => $inviter->id,
        'circle_id' => $circle->id,
    ]);

    $inviter->notify(new CircleInvitationAcceptedNotification($invitation, 'Alice'));

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();

    expect($email->getSubject())->toBe('Alice is lid geworden van Family')
        ->and($email->getHtmlBody())->toContain('Hallo '.$inviter->name)
        ->and($email->getHtmlBody())->toContain('Goed nieuws: Alice heeft je uitnodiging geaccepteerd')
        ->and($email->getHtmlBody())->toContain('is lid geworden van de kring "Family"');
});
