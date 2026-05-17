<?php

namespace App\Http\Resources;

use App\Models\CircleInviteLink;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin CircleInviteLink */
#[OA\Schema(
    schema: 'CircleInviteLinkPreview',
    properties: [
        new OA\Property(property: 'valid', type: 'boolean'),
        new OA\Property(property: 'reason', type: 'string', enum: ['expired', 'revoked', 'maxed'], nullable: true),
        new OA\Property(property: 'circle', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'photo', type: 'string', nullable: true),
            new OA\Property(property: 'members_count', type: 'integer'),
        ]),
        new OA\Property(property: 'inviter', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'username', type: 'string'),
            new OA\Property(property: 'avatar', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'member_preview', type: 'array', items: new OA\Items(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'avatar', type: 'string', nullable: true),
            ],
        )),
    ],
)]
class CircleInviteLinkPreviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $reason = $this->invalidReason();
        $circle = $this->circle;

        $previewMembers = $circle->members
            ->take(3)
            ->map(fn ($member) => [
                'name' => $member->name,
                'avatar' => MediaUrl::sign($member->avatar),
            ])
            ->values();

        return [
            'valid' => $reason === null,
            'reason' => $reason,
            'circle' => [
                'id' => $circle->id,
                'name' => $circle->name,
                'photo' => MediaUrl::sign($circle->photo),
                'members_count' => ($circle->members_count ?? $circle->members->count()) + 1,
            ],
            'inviter' => [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
                'username' => $this->createdBy->username,
                'avatar' => MediaUrl::sign($this->createdBy->avatar),
            ],
            'member_preview' => $previewMembers,
        ];
    }
}
