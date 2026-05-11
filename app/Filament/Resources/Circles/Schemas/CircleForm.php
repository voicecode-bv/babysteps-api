<?php

namespace App\Filament\Resources\Circles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class CircleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('user_id')
                    ->label('Owner')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Toggle::make('members_can_invite')
                    ->helperText('Allow non-owner members to invite others to this circle.')
                    ->default(false),
                Toggle::make('members_can_view_members')
                    ->label('Members can see other members')
                    ->helperText('When disabled, non-owner members only see the owner and themselves in the member list.')
                    ->default(true),
            ]);
    }
}
