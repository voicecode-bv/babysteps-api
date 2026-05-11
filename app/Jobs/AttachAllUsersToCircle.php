<?php

namespace App\Jobs;

use App\Models\Circle;
use App\Models\User;
use App\Services\MemberPersonSyncer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AttachAllUsersToCircle implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public Circle $circle) {}

    public function handle(MemberPersonSyncer $syncer): void
    {
        User::query()
            ->where('id', '!=', $this->circle->user_id)
            ->select('id', 'name', 'avatar', 'avatar_thumbnail')
            ->chunkById(500, function ($users) use ($syncer) {
                $syncer->attachUsersBulk($this->circle, $users);
            });
    }
}
