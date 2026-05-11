<?php

namespace App\Filament\Resources\Circles\Pages;

use App\Filament\Resources\Circles\CircleResource;
use App\Jobs\AttachAllUsersToCircle;
use App\Models\Circle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditCircle extends EditRecord
{
    protected static string $resource = CircleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('attachAllUsers')
                ->label('Attach all existing users')
                ->icon(Heroicon::OutlinedUsers)
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Queue a background job that attaches every existing user (except the owner) to this circle. Users already in the circle are skipped.')
                ->modalSubmitActionLabel('Queue backfill')
                ->action(function (Circle $record) {
                    AttachAllUsersToCircle::dispatch($record);

                    Notification::make()
                        ->title('Backfill queued')
                        ->body("Existing users will be attached to “{$record->name}” in the background.")
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
