<?php

use App\Filament\Resources\WaitingListEntries\Pages\CreateWaitingListEntry;
use App\Filament\Resources\WaitingListEntries\Pages\EditWaitingListEntry;
use App\Filament\Resources\WaitingListEntries\Pages\ListWaitingListEntries;
use App\Models\User;
use App\Models\WaitingListEntry;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists waiting list entries newest first', function () {
    $older = WaitingListEntry::factory()->create(['created_at' => now()->subDay()]);
    $newer = WaitingListEntry::factory()->create(['created_at' => now()]);

    Livewire::test(ListWaitingListEntries::class)
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true);
});

it('filters entries by email search', function () {
    $match = WaitingListEntry::factory()->create(['email' => 'wanted@example.test']);
    $other = WaitingListEntry::factory()->create(['email' => 'someone@example.test']);

    Livewire::test(ListWaitingListEntries::class)
        ->searchTable('wanted@')
        ->assertCanSeeTableRecords([$match])
        ->assertCanNotSeeTableRecords([$other]);
});

it('creates a new entry from the admin', function () {
    Livewire::test(CreateWaitingListEntry::class)
        ->fillForm(['email' => 'new@example.test'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(WaitingListEntry::query()->where('email', 'new@example.test')->exists())->toBeTrue();
});

it('rejects duplicate emails', function () {
    WaitingListEntry::factory()->create(['email' => 'taken@example.test']);

    Livewire::test(CreateWaitingListEntry::class)
        ->fillForm(['email' => 'taken@example.test'])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('updates an existing entry', function () {
    $entry = WaitingListEntry::factory()->create(['email' => 'old@example.test']);

    Livewire::test(EditWaitingListEntry::class, ['record' => $entry->getKey()])
        ->fillForm(['email' => 'new@example.test'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($entry->refresh()->email)->toBe('new@example.test');
});
