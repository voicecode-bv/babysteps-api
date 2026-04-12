<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDeviceInfoRequest extends FormRequest
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
            'app_name' => ['required', 'string', 'max:255'],
            'package_name' => ['required', 'string', 'max:255'],
            'version' => ['required', 'string', 'max:50'],
            'build_number' => ['required', 'string', 'max:50'],
            'installer_store' => ['nullable', 'string', 'max:255'],
        ];
    }
}
