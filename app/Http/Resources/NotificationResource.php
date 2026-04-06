<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        if (isset($data['user_avatar'])) {
            $data['user_avatar'] = MediaUrl::sign($data['user_avatar']);
        }

        if (isset($data['post_media_url'])) {
            $data['post_media_url'] = MediaUrl::sign($data['post_media_url']);
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}
