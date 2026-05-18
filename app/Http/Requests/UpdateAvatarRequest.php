<?php

namespace App\Http\Requests;

use App\Rules\MaxImageDimensions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
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
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,heic,heif', 'max:10240', new MaxImageDimensions(4096, 4096)],
        ];
    }
}
