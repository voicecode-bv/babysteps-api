<?php

namespace App\Filament\Resources\EmailTemplates\Schemas;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Models\EmailTemplate;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class EmailTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->heading(fn (?EmailTemplate $record): string => $record
                        ? (EmailTemplateRegistry::get($record->key)['label'] ?? $record->key)
                        : 'Email template')
                    ->description(fn (?EmailTemplate $record): string => $record
                        ? (EmailTemplateRegistry::get($record->key)['description'] ?? '')
                        : '')
                    ->schema([
                        View::make('filament.email-templates.placeholders')
                            ->viewData(fn (?EmailTemplate $record): array => [
                                'placeholders' => $record
                                    ? (EmailTemplateRegistry::get($record->key)['placeholders'] ?? [])
                                    : [],
                            ]),
                    ]),

                Tabs::make('locale')
                    ->columnSpanFull()
                    ->tabs([
                        self::localeTab('Nederlands', 'nl'),
                        self::localeTab('English', 'en'),
                        self::localeTab('Français', 'fr'),
                    ]),
            ]);
    }

    private static function localeTab(string $label, string $locale): Tab
    {
        $subjectField = "subject_{$locale}";
        $bodyField = "body_{$locale}";

        return Tab::make($label)
            ->schema([
                TextInput::make($subjectField)
                    ->label('Subject')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->columnSpanFull(),

                MarkdownEditor::make($bodyField)
                    ->label('Body (markdown)')
                    ->required()
                    ->live(debounce: 500)
                    ->disableToolbarButtons(['attachFiles'])
                    ->columnSpanFull(),

                View::make('filament.email-templates.preview')
                    ->columnSpanFull()
                    ->viewData(fn (Get $get) => [
                        'subject' => (string) ($get($subjectField) ?? ''),
                        'body' => (string) ($get($bodyField) ?? ''),
                        'locale' => $locale,
                    ]),
            ]);
    }
}
