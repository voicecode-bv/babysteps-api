<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification as FilamentNotification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;
use Kreait\Firebase\Messaging\SendReport;

class PushNotificationsTest extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'push-notifications-test';

    protected string $view = 'filament.pages.push-notifications-test';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = 'Push notifications test';

    protected static ?string $title = 'Push notifications test';

    protected static ?int $navigationSort = 91;

    /**
     * @var array<string, mixed>
     */
    public array $sendData = [];

    /**
     * @var list<array{token: string, success: bool, error: ?string}>
     */
    public array $lastResults = [];

    public ?string $lastSummary = null;

    public function mount(): void
    {
        $this->sendForm->fill([
            'title' => 'Test notification',
            'body' => 'This is a test push from the Filament admin.',
            'data_type' => 'test',
        ]);
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->admin === true;
    }

    /**
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return ['sendForm'];
    }

    public function sendForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Verstuur test-push')
                    ->description('Kies een gebruiker en verstuur direct een push naar al diens geregistreerde devices. Negeert notification preferences.')
                    ->schema([
                        Select::make('user_id')
                            ->label('User')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => User::query()
                                ->where(fn ($q) => $q->where('email', 'ilike', "%{$search}%")->orWhere('username', 'ilike', "%{$search}%")->orWhere('name', 'ilike', "%{$search}%"))
                                ->limit(20)
                                ->get()
                                ->mapWithKeys(fn (User $u): array => [$u->id => $this->userLabel($u)])
                                ->all())
                            ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value) ? $this->userLabel(User::query()->find($value)) : null),
                        TextInput::make('title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(120),
                        Textarea::make('body')
                            ->label('Body')
                            ->required()
                            ->rows(3)
                            ->autosize(false)
                            ->maxLength(500),
                        TextInput::make('data_type')
                            ->label('data.type')
                            ->helperText('Komt mee als "type" in de data-payload, handig om in de app op te reageren.')
                            ->maxLength(64),
                    ]),
            ])
            ->statePath('sendData');
    }

    public function send(): void
    {
        $data = $this->sendForm->getState();

        $user = User::query()->findOrFail($data['user_id']);

        $tokens = $user->deviceTokens()->pluck('token')->all();

        if ($tokens === []) {
            $this->lastResults = [];
            $this->lastSummary = 'Deze gebruiker heeft geen geregistreerde device tokens.';
            FilamentNotification::make()->title('Geen device tokens')->warning()->send();

            return;
        }

        $payload = [
            'type' => trim((string) ($data['data_type'] ?? '')) !== '' ? (string) $data['data_type'] : 'test',
            'sent_at' => now()->toIso8601String(),
        ];

        $message = CloudMessage::new()
            ->withNotification(FcmNotification::create($data['title'], $data['body']))
            ->withData($payload);

        try {
            $report = app(Messaging::class)->sendMulticast($message, $tokens);
        } catch (\Throwable $e) {
            $this->lastResults = [];
            $this->lastSummary = 'Send failed: '.$e->getMessage();
            FilamentNotification::make()->title('Push send mislukt')->body($e->getMessage())->danger()->send();

            return;
        }

        $this->lastResults = array_map(
            fn (SendReport $r): array => [
                'token' => $r->target()->value(),
                'success' => $r->isSuccess(),
                'error' => $r->isFailure() ? $r->error()?->getMessage() : null,
            ],
            $report->getItems(),
        );

        $successes = $report->successes()->count();
        $failures = $report->failures()->count();

        $this->lastSummary = sprintf('Verstuurd naar %d device(s): %d succes, %d mislukt.', count($tokens), $successes, $failures);

        FilamentNotification::make()
            ->title('Push verstuurd')
            ->body($this->lastSummary)
            ->color($failures === 0 ? 'success' : 'warning')
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getSendFormActions(): array
    {
        return [Action::make('send')->label('Verstuur push')->action('send')];
    }

    private function userLabel(User $user): string
    {
        return "{$user->name} <{$user->email}>";
    }
}
