<?php

namespace App\Filament\Resources\WaitingListEntries;

use App\Filament\Resources\WaitingListEntries\Pages\CreateWaitingListEntry;
use App\Filament\Resources\WaitingListEntries\Pages\EditWaitingListEntry;
use App\Filament\Resources\WaitingListEntries\Pages\ListWaitingListEntries;
use App\Filament\Resources\WaitingListEntries\Schemas\WaitingListEntryForm;
use App\Filament\Resources\WaitingListEntries\Tables\WaitingListEntriesTable;
use App\Models\WaitingListEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class WaitingListEntryResource extends Resource
{
    protected static ?string $model = WaitingListEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Waiting list';

    protected static ?string $modelLabel = 'waiting list entry';

    protected static ?string $pluralModelLabel = 'waiting list entries';

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return WaitingListEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaitingListEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitingListEntries::route('/'),
            'create' => CreateWaitingListEntry::route('/create'),
            'edit' => EditWaitingListEntry::route('/{record}/edit'),
        ];
    }
}
