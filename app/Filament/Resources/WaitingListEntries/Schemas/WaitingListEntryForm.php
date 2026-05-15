<?php

namespace App\Filament\Resources\WaitingListEntries\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class WaitingListEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
            ]);
    }
}
