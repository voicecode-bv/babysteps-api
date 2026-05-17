<?php

namespace App\Http\Resources;

use App\Models\CircleInviteLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin CircleInviteLink */
#[OA\Schema(
    schema: 'CircleInviteLink',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'token', type: 'string'),
        new OA\Property(property: 'url', type: 'string', description: 'Full shareable URL pointing to the SPA landing page.'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'max_uses', type: 'integer', nullable: true),
        new OA\Property(property: 'uses_count', type: 'integer'),
        new OA\Property(property: 'revoked_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_by', type: 'object', properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'username', type: 'string'),
        ]),
    ],
)]
class CircleInviteLinkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'token' => $this->token,
            'url' => rtrim((string) config('app.frontend_url'), '/').'/join/'.$this->token,
            'expires_at' => $this->expires_at,
            'max_uses' => $this->max_uses,
            'uses_count' => $this->uses_count,
            'revoked_at' => $this->revoked_at,
            'created_at' => $this->created_at,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'username' => $this->createdBy->username,
            ]),
        ];
    }
}
