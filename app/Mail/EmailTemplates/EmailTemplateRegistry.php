<?php

namespace App\Mail\EmailTemplates;

use App\Models\EmailTemplate;

class EmailTemplateRegistry
{
    public const CIRCLE_INVITATION = 'circle_invitation';

    public const CIRCLE_INVITATION_ACCEPTED = 'circle_invitation_accepted';

    public const CIRCLE_OWNERSHIP_TRANSFER_REQUESTED = 'circle_ownership_transfer_requested';

    public const CIRCLE_OWNERSHIP_TRANSFER_ACCEPTED = 'circle_ownership_transfer_accepted';

    public const CIRCLE_OWNERSHIP_TRANSFER_DECLINED = 'circle_ownership_transfer_declined';

    public const GDPR_EXPORT_READY = 'gdpr_export_ready';

    public const EARLY_ADOPTERS = 'early_adopters';

    public const SUBSCRIPTION_STARTED = 'subscription_started';

    public const PASSWORD_RESET = 'password_reset';

    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     format: string,
     *     mailer?: string|null,
     *     placeholders: array<string, string>,
     *     samples: array<string, string>,
     *     defaults: array<string, array{subject: string, body: string}>,
     * }>
     */
    public static function all(): array
    {
        return [
            self::CIRCLE_INVITATION => [
                'label' => 'Circle invitation (non-user)',
                'description' => 'Sent to an email address that does not yet have an account when someone invites them.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'inviter_name' => 'Name of the person sending the invitation.',
                    'circle_name' => 'Name of the circle the recipient is invited to.',
                ],
                'samples' => [
                    'inviter_name' => 'Sophie de Vries',
                    'circle_name' => 'Family',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => '{inviter_name} heeft je uitgenodigd voor {circle_name}',
                        'body' => "# Hallo!\n\n{inviter_name} heeft je uitgenodigd om lid te worden van de kring \"{circle_name}\".\n\nHeb je nog geen account? Registreer je dan eerst.",
                    ],
                    'en' => [
                        'subject' => '{inviter_name} has invited you to {circle_name}',
                        'body' => "# Hello!\n\n{inviter_name} has invited you to join the circle \"{circle_name}\".\n\nIf you don't have an account yet, please register first.",
                    ],
                    'fr' => [
                        'subject' => '{inviter_name} vous a invité à rejoindre {circle_name}',
                        'body' => "# Bonjour !\n\n{inviter_name} vous a invité à rejoindre le cercle « {circle_name} ».\n\nSi vous n'avez pas encore de compte, veuillez d'abord vous inscrire.",
                    ],
                ],
            ],

            self::CIRCLE_INVITATION_ACCEPTED => [
                'label' => 'Circle invitation accepted',
                'description' => 'Sent to the inviter when someone accepts their circle invitation.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the recipient (the inviter).',
                    'accepted_by_name' => 'Name of the user who accepted the invitation.',
                    'circle_name' => 'Name of the circle.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'accepted_by_name' => 'Sophie de Vries',
                    'circle_name' => 'Family',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => '{accepted_by_name} is lid geworden van {circle_name}',
                        'body' => "# Hallo {recipient_name}!\n\nGoed nieuws: {accepted_by_name} heeft je uitnodiging geaccepteerd en is lid geworden van de kring \"{circle_name}\".",
                    ],
                    'en' => [
                        'subject' => '{accepted_by_name} has joined {circle_name}',
                        'body' => "# Hello {recipient_name}!\n\nGood news: {accepted_by_name} has accepted your invitation and joined the circle \"{circle_name}\".",
                    ],
                    'fr' => [
                        'subject' => '{accepted_by_name} a rejoint {circle_name}',
                        'body' => "# Bonjour {recipient_name} !\n\nBonne nouvelle : {accepted_by_name} a accepté votre invitation et a rejoint le cercle \"{circle_name}\".",
                    ],
                ],
            ],

            self::CIRCLE_OWNERSHIP_TRANSFER_REQUESTED => [
                'label' => 'Circle ownership transfer requested',
                'description' => 'Sent when the current owner of a circle requests an ownership transfer to another member.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the recipient (the user receiving the email).',
                    'from_name' => 'Name of the current owner who initiated the transfer.',
                    'circle_name' => 'Name of the circle.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'from_name' => 'Sophie de Vries',
                    'circle_name' => 'Family',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => '{from_name} wil "{circle_name}" aan jou overdragen',
                        'body' => "# Hallo {recipient_name}!\n\n{from_name} wil het eigenaarschap van de kring \"{circle_name}\" aan jou overdragen.\n\nOpen de app om dit verzoek te accepteren of te weigeren.",
                    ],
                    'en' => [
                        'subject' => '{from_name} wants to transfer "{circle_name}" to you',
                        'body' => "# Hello {recipient_name}!\n\n{from_name} wants to transfer ownership of the circle \"{circle_name}\" to you.\n\nOpen the app to accept or decline this request.",
                    ],
                    'fr' => [
                        'subject' => '{from_name} souhaite vous transférer "{circle_name}"',
                        'body' => "# Bonjour {recipient_name} !\n\n{from_name} souhaite vous transférer la propriété du cercle \"{circle_name}\".\n\nOuvrez l'application pour accepter ou refuser cette demande.",
                    ],
                ],
            ],

            self::CIRCLE_OWNERSHIP_TRANSFER_ACCEPTED => [
                'label' => 'Circle ownership transfer accepted',
                'description' => 'Sent to the previous owner when the recipient accepts the ownership transfer.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the recipient (the previous owner).',
                    'to_name' => 'Name of the user who accepted ownership.',
                    'circle_name' => 'Name of the circle.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'to_name' => 'Sophie de Vries',
                    'circle_name' => 'Family',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => '{to_name} is nu de eigenaar van {circle_name}',
                        'body' => "# Hallo {recipient_name}!\n\n{to_name} heeft je eigenaarschap overdracht geaccepteerd en is nu de eigenaar van de kring \"{circle_name}\".",
                    ],
                    'en' => [
                        'subject' => '{to_name} is now the owner of {circle_name}',
                        'body' => "# Hello {recipient_name}!\n\n{to_name} accepted your ownership transfer and is now the owner of the circle \"{circle_name}\".",
                    ],
                    'fr' => [
                        'subject' => '{to_name} est désormais propriétaire de {circle_name}',
                        'body' => "# Bonjour {recipient_name} !\n\n{to_name} a accepté votre transfert de propriété et est désormais le propriétaire du cercle \"{circle_name}\".",
                    ],
                ],
            ],

            self::CIRCLE_OWNERSHIP_TRANSFER_DECLINED => [
                'label' => 'Circle ownership transfer declined',
                'description' => 'Sent to the requesting owner when the recipient declines the ownership transfer.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the recipient (the current owner).',
                    'to_name' => 'Name of the user who declined ownership.',
                    'circle_name' => 'Name of the circle.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'to_name' => 'Sophie de Vries',
                    'circle_name' => 'Family',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => '{to_name} heeft het eigenaarschap van {circle_name} geweigerd',
                        'body' => "# Hallo {recipient_name}!\n\n{to_name} heeft de eigenaarschap overdracht van de kring \"{circle_name}\" geweigerd. Jij blijft de eigenaar.",
                    ],
                    'en' => [
                        'subject' => '{to_name} declined ownership of {circle_name}',
                        'body' => "# Hello {recipient_name}!\n\n{to_name} declined the ownership transfer of the circle \"{circle_name}\". You remain the owner.",
                    ],
                    'fr' => [
                        'subject' => '{to_name} a refusé la propriété de {circle_name}',
                        'body' => "# Bonjour {recipient_name} !\n\n{to_name} a refusé le transfert de propriété du cercle \"{circle_name}\". Vous restez le propriétaire.",
                    ],
                ],
            ],

            self::GDPR_EXPORT_READY => [
                'label' => 'GDPR export ready',
                'description' => 'Sent to a user when their personal data export is ready for download.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the recipient.',
                    'download_url' => 'Temporary URL where the user can download their export.',
                    'hours' => 'Number of hours until the download URL expires.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'download_url' => 'https://example.test/exports/sample.zip',
                    'hours' => '24',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => 'Je data-export staat klaar',
                        'body' => "# Hallo {recipient_name}!\n\nJe persoonlijke data-export staat klaar om te downloaden.\n\n[Download je gegevens]({download_url})\n\nDeze link verloopt over {hours} uur.\n\nHeb je dit niet aangevraagd? Dan kun je deze e-mail negeren.",
                    ],
                    'en' => [
                        'subject' => 'Your data export is ready',
                        'body' => "# Hello {recipient_name}!\n\nYour personal data export is ready to download.\n\n[Download your data]({download_url})\n\nThis link expires in {hours} hours.\n\nIf you did not request this, you can ignore this email.",
                    ],
                    'fr' => [
                        'subject' => 'Votre export de données est prêt',
                        'body' => "# Bonjour {recipient_name} !\n\nVotre export de données personnelles est prêt à être téléchargé.\n\n[Télécharger vos données]({download_url})\n\nCe lien expire dans {hours} heures.\n\nSi vous n'avez pas fait cette demande, vous pouvez ignorer cet e-mail.",
                    ],
                ],
            ],

            self::EARLY_ADOPTERS => [
                'label' => 'Early adopters welcome mail',
                'description' => 'One-off HTML mail naar handmatig opgegeven ontvanger. Geen wrapper, geen auto-signature — afsluiting zit in de HTML zelf.',
                'format' => EmailTemplate::FORMAT_RAW_HTML,
                'mailer' => 'postmark_broadcast',
                'placeholders' => [],
                'samples' => [],
                'defaults' => [
                    'nl' => [
                        'subject' => 'Welkom bij Innerr — early access',
                        'body' => '',
                    ],
                    'en' => ['subject' => '', 'body' => ''],
                    'fr' => ['subject' => '', 'body' => ''],
                ],
            ],

            self::PASSWORD_RESET => [
                'label' => 'Password reset',
                'description' => 'Sent when a user requests a password reset link. The {reset_url} placeholder is required — it is the link the user clicks to set a new password.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the recipient.',
                    'reset_url' => 'Link the user clicks to reset their password. Must be included as a markdown link.',
                    'minutes' => 'Number of minutes the reset link stays valid.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'reset_url' => 'https://innerr.app/password-reset?token=sample-token&email=jordan%40example.com',
                    'minutes' => '60',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => 'Reset je wachtwoord',
                        'body' => "# Hallo {recipient_name}!\n\nJe ontvangt deze e-mail omdat we een verzoek hebben gekregen om het wachtwoord van je account te resetten.\n\n[Wachtwoord resetten]({reset_url})\n\nDeze resetlink verloopt over {minutes} minuten.\n\nHeb je geen wachtwoord-reset aangevraagd? Dan hoef je niets te doen.",
                    ],
                    'en' => [
                        'subject' => 'Reset your password',
                        'body' => "# Hello {recipient_name}!\n\nYou are receiving this email because we received a password reset request for your account.\n\n[Reset Password]({reset_url})\n\nThis password reset link will expire in {minutes} minutes.\n\nIf you did not request a password reset, no further action is required.",
                    ],
                    'fr' => [
                        'subject' => 'Réinitialisez votre mot de passe',
                        'body' => "# Bonjour {recipient_name} !\n\nVous recevez cet e-mail parce que nous avons reçu une demande de réinitialisation du mot de passe de votre compte.\n\n[Réinitialiser le mot de passe]({reset_url})\n\nCe lien de réinitialisation expirera dans {minutes} minutes.\n\nSi vous n'avez pas demandé de réinitialisation, aucune action n'est requise.",
                    ],
                ],
            ],

            self::SUBSCRIPTION_STARTED => [
                'label' => 'Subscription started (thank you)',
                'description' => 'Sent to a user when their subscription is activated for the first time. Introduces Innerr Geeft.',
                'format' => EmailTemplate::FORMAT_MARKDOWN_MESSAGE,
                'placeholders' => [
                    'recipient_name' => 'Name of the subscriber.',
                    'donate_url' => 'Public landing page that explains Innerr Geeft.',
                ],
                'samples' => [
                    'recipient_name' => 'Jordan',
                    'donate_url' => 'https://innerr.app/nl/doneren/',
                ],
                'defaults' => [
                    'nl' => [
                        'subject' => 'Bedankt voor je abonnement op Innerr',
                        'body' => "# Bedankt {recipient_name}!\n\nFijn dat je een abonnement op Innerr hebt afgesloten. Met jouw bijdrage kunnen we Innerr blijven verbeteren en uitbouwen.\n\n## Innerr Geeft\n\nWist je dat een deel van elk abonnement direct ten goede komt aan goede doelen? Via **Innerr Geeft** doneren we een vast percentage van onze inkomsten aan initiatieven die bijdragen aan welzijn, verbinding en mentale gezondheid.\n\nMeer lezen over Innerr Geeft en welke projecten we steunen? Kijk op [innerr.app/nl/doneren]({donate_url}).\n\nNogmaals dank dat je deel uitmaakt van Innerr.",
                    ],
                    'en' => [
                        'subject' => 'Thank you for subscribing to Innerr',
                        'body' => "# Thank you {recipient_name}!\n\nWe're glad you've started a subscription with Innerr. Your support helps us keep improving and growing the app.\n\n## Innerr Gives\n\nDid you know that part of every subscription goes directly to charity? Through **Innerr Gives** we donate a fixed percentage of our revenue to initiatives that promote wellbeing, connection and mental health.\n\nCurious which projects we support? Read more at [innerr.app/en/donate]({donate_url}).\n\nThanks again for being part of Innerr.",
                    ],
                    'fr' => [
                        'subject' => 'Merci pour votre abonnement à Innerr',
                        'body' => "# Merci {recipient_name} !\n\nNous sommes ravis que vous ayez souscrit un abonnement à Innerr. Votre soutien nous permet de continuer à améliorer et à développer l'application.\n\n## Innerr Donne\n\nSaviez-vous qu'une partie de chaque abonnement est directement reversée à des œuvres caritatives ? Grâce à **Innerr Donne**, nous reversons un pourcentage fixe de nos revenus à des initiatives qui favorisent le bien-être, le lien social et la santé mentale.\n\nEnvie d'en savoir plus sur les projets que nous soutenons ? Rendez-vous sur [innerr.app/fr/don]({donate_url}).\n\nMerci encore de faire partie d'Innerr.",
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *     label: string,
     *     description: string,
     *     format: string,
     *     mailer?: string|null,
     *     placeholders: array<string, string>,
     *     samples: array<string, string>,
     *     defaults: array<string, array{subject: string, body: string}>,
     * }|null
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
