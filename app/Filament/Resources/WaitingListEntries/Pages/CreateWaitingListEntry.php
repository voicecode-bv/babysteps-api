<?php

namespace App\Filament\Resources\WaitingListEntries\Pages;

use App\Filament\Resources\WaitingListEntries\WaitingListEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWaitingListEntry extends CreateRecord
{
    protected static string $resource = WaitingListEntryResource::class;
}
