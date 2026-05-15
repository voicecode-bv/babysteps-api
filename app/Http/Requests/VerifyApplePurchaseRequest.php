<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyApplePurchaseRequest extends FormRequest
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
            'signed_transaction' => ['required_without:original_transaction_id', 'nullable', 'string', 'max:8192'],
            'original_transaction_id' => ['required_without:signed_transaction', 'nullable', 'string', 'max:255'],
        ];
    }
}
