<?php

namespace App\Enums;

enum NotificationPreference: string
{
    case PostLiked = 'post_liked';
    case PostCommented = 'post_commented';
    case CommentLiked = 'comment_liked';
    case NewCirclePost = 'new_circle_post';
    case CircleInvitationAccepted = 'circle_invitation_accepted';

    /**
     * @return array<string, bool>
     */
    public static function defaults(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => true])
            ->all();
    }
}
