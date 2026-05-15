<?php

namespace App\Filament\Resources\WaitingListEntries\Pages;

use App\Filament\Resources\WaitingListEntries\WaitingListEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWaitingListEntry extends EditRecord
{
    protected static string $resource = WaitingListEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
