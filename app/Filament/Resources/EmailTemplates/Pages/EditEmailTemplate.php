<?php

namespace App\Filament\Resources\EmailTemplates\Pages;

use App\Filament\Resources\EmailTemplates\EmailTemplateResource;
use App\Mail\TestEmailTemplateMail;
use App\Models\EmailTemplate;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class EditEmailTemplate extends EditRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Send test email')
                ->icon(Heroicon::PaperAirplane)
                ->color('gray')
                ->modalHeading('Send a test of this template')
                ->modalDescription('Save your changes first — the test uses the currently saved template. Placeholders are filled with sample values and the signature uses a random name.')
                ->mountUsing(fn ($schema) => $schema->fill([
                    'email' => Auth::user()?->email,
                    'locale' => 'nl',
                ]))
                ->schema([
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->label('Send to')
                        ->maxLength(255),
                    Select::make('locale')
                        ->required()
                        ->options([
                            'nl' => 'Nederlands',
                            'en' => 'English',
                            'fr' => 'Français',
                        ]),
                ])
                ->action(function (array $data, EmailTemplate $record): void {
                    Mail::to($data['email'])->send(new TestEmailTemplateMail(
                        templateKey: $record->key,
                        templateLocale: $data['locale'],
                    ));

                    Notification::make()
                        ->title('Test email sent')
                        ->body('Sent to '.$data['email'].' in '.strtoupper($data['locale']).'.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
