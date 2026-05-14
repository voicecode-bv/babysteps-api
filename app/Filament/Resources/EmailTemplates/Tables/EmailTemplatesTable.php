<?php

namespace App\Filament\Resources\EmailTemplates\Tables;

use App\Mail\EmailTemplates\EmailTemplateRegistry;
use App\Models\EmailTemplate;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmailTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Template')
                    ->state(fn (EmailTemplate $record): string => EmailTemplateRegistry::get($record->key)['label'] ?? $record->key)
                    ->searchable(query: fn ($query, string $search) => $query->where('key', 'like', "%{$search}%"))
                    ->wrap(),
                TextColumn::make('key')
                    ->label('Key')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('subject_en')
                    ->label('Subject (EN)')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Last updated')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('key')
            ->recordActions([
                EditAction::make(),
            ])
            ->paginated(false);
    }
}
