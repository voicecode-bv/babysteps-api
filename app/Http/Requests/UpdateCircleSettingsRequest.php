<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCircleSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'members_can_invite' => ['required_without_all:members_can_view_members,members_can_download', 'boolean'],
            'members_can_view_members' => ['required_without_all:members_can_invite,members_can_download', 'boolean'],
            'members_can_download' => ['required_without_all:members_can_invite,members_can_view_members', 'boolean'],
        ];
    }
}
