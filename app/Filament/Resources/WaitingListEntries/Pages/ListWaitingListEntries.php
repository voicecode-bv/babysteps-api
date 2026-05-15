<?php

namespace App\Filament\Resources\WaitingListEntries\Pages;

use App\Filament\Resources\WaitingListEntries\WaitingListEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWaitingListEntries extends ListRecords
{
    protected static string $resource = WaitingListEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
