<?php

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Mail\EmailTemplates\EmailTemplateRenderer;
use App\Models\EmailTemplate;

it('renders the requested locale and replaces placeholders', function () {
    EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->update([
        'subject_nl' => '{inviter_name} heeft je uitgenodigd',
        'body_nl' => 'Hallo, {inviter_name} heeft je uitgenodigd.',
        'subject_en' => '{inviter_name} has invited you',
        'body_en' => 'Hello, {inviter_name} has invited you.',
    ]);

    $rendered = app(EmailTemplateRenderer::class)->render(
        EmailTemplateRegistry::CIRCLE_INVITATION,
        ['inviter_name' => 'Sophie'],
        'nl',
    );

    expect($rendered['subject'])->toBe('Sophie heeft je uitgenodigd')
        ->and($rendered['body'])->toBe('Hallo, Sophie heeft je uitgenodigd.');
});

it('falls back to english when the requested locale is empty', function () {
    EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->update([
        'subject_fr' => null,
        'body_fr' => '',
        'subject_en' => '{inviter_name} has invited you',
        'body_en' => 'Hello, {inviter_name} has invited you.',
    ]);

    $rendered = app(EmailTemplateRenderer::class)->render(
        EmailTemplateRegistry::CIRCLE_INVITATION,
        ['inviter_name' => 'Sophie'],
        'fr',
    );

    expect($rendered['subject'])->toBe('Sophie has invited you')
        ->and($rendered['body'])->toBe('Hello, Sophie has invited you.');
});

it('falls back to registry defaults when no database row exists', function () {
    EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->delete();

    $rendered = app(EmailTemplateRenderer::class)->render(
        EmailTemplateRegistry::CIRCLE_INVITATION,
        ['inviter_name' => 'Sophie'],
        'en',
    );

    expect($rendered['subject'])->toBe('Sophie has invited you')
        ->and($rendered['body'])->toContain('Sophie has invited you to join their circles.');
});

it('leaves unknown placeholders untouched', function () {
    EmailTemplate::query()->where('key', EmailTemplateRegistry::CIRCLE_INVITATION)->update([
        'subject_en' => '{inviter_name} invited {nonexistent}',
        'body_en' => 'Body {missing}.',
    ]);

    $rendered = app(EmailTemplateRenderer::class)->render(
        EmailTemplateRegistry::CIRCLE_INVITATION,
        ['inviter_name' => 'Sophie'],
        'en',
    );

    expect($rendered['subject'])->toBe('Sophie invited {nonexistent}')
        ->and($rendered['body'])->toBe('Body {missing}.');
});
