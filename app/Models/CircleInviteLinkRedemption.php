<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['invite_link_id', 'user_id', 'redeemed_at'])]
class CircleInviteLinkRedemption extends Model
{
    use HasUuids;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'redeemed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CircleInviteLink, $this>
     */
    public function inviteLink(): BelongsTo
    {
        return $this->belongsTo(CircleInviteLink::class, 'invite_link_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
