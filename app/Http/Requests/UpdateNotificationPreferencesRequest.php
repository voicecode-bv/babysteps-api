<?php

namespace App\Http\Requests;

use App\Enums\NotificationPreference;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNotificationPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $keys = array_column(NotificationPreference::cases(), 'value');

        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => ['required', 'boolean']])
            ->all();
    }
}
