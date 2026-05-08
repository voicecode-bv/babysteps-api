<?php

namespace App\Enums;

enum OnboardingStep: string
{
    case Intro = 'intro';
    case FirstCircle = 'first_circle';
    case InviteMembers = 'invite_members';
    case Notifications = 'notifications';
}
