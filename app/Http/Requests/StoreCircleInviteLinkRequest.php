<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCircleInviteLinkRequest extends FormRequest
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
        return [
            'expires_in_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:365'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
        ];
    }
}
