<?php

namespace App\Http\Resources;

use App\Models\Comment;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Comment */
#[OA\Schema(
    schema: 'Comment',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'is_visible', type: 'boolean'),
        new OA\Property(property: 'body', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'user', type: 'object', nullable: true, properties: [
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'username', type: 'string'),
            new OA\Property(property: 'avatar', type: 'string', nullable: true),
        ]),
        new OA\Property(property: 'likes_count', type: 'integer', nullable: true),
        new OA\Property(property: 'is_liked', type: 'boolean', nullable: true),
    ],
)]
class CommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Comments waar viewer en auteur geen circle delen worden geredacteerd
        // tot het minimum dat de client nodig heeft om "X verborgen" te tonen.
        // Default true zodat single-resource responses (zoals POST store) altijd
        // de volledige payload teruggeven aan de auteur zelf.
        $isVisible = (bool) ($this->is_visible ?? true);

        if (! $isVisible) {
            return [
                'id' => $this->id,
                'is_visible' => false,
                'created_at' => $this->created_at,
            ];
        }

        return [
            'id' => $this->id,
            'is_visible' => true,
            'body' => $this->body,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'avatar' => MediaUrl::sign($this->user->avatar),
            ],
            'likes_count' => $this->likes_count ?? 0,
            'is_liked' => (bool) ($this->is_liked ?? false),
        ];
    }
}
