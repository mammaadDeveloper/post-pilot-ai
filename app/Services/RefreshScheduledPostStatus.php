<?php

namespace App\Services;

use App\Enums\PublicationTargetStatus;
use App\Enums\ScheduledPostStatus;
use App\Models\ScheduledPost;
use Illuminate\Support\Facades\DB;

class RefreshScheduledPostStatus
{
    public function handle(int $scheduledPostId): void
    {
        DB::transaction(function () use ($scheduledPostId): void {
            $post = ScheduledPost::query()
                ->whereKey($scheduledPostId)
                ->lockForUpdate()
                ->with('targets')
                ->first();

            if ($post === null || $post->status === ScheduledPostStatus::Cancelled) {
                return;
            }

            $statuses = $post->targets->pluck('status');
            $hasSuccess = $statuses->contains(PublicationTargetStatus::Published);
            $hasActive = $statuses->contains(fn (PublicationTargetStatus $status): bool => in_array(
                $status,
                [
                    PublicationTargetStatus::Pending,
                    PublicationTargetStatus::Queued,
                    PublicationTargetStatus::Processing,
                ],
                true,
            ));
            $status = match (true) {
                $statuses->isNotEmpty() && $statuses->every(fn (PublicationTargetStatus $item): bool => $item === PublicationTargetStatus::Published) => ScheduledPostStatus::Published,
                $statuses->isNotEmpty() && $statuses->every(fn (PublicationTargetStatus $item): bool => $item === PublicationTargetStatus::Cancelled) => ScheduledPostStatus::Cancelled,
                $statuses->contains(PublicationTargetStatus::OutcomeUnknown) => ScheduledPostStatus::OutcomeUnknown,
                $hasActive && ($hasSuccess || $statuses->contains(PublicationTargetStatus::Processing)) => ScheduledPostStatus::Publishing,
                $hasActive => ScheduledPostStatus::Pending,
                $hasSuccess => ScheduledPostStatus::PartiallyPublished,
                default => ScheduledPostStatus::Failed,
            };

            $post->update([
                'status' => $status,
                'published_at' => $status === ScheduledPostStatus::Published ? now() : null,
                'safe_error' => $status === ScheduledPostStatus::OutcomeUnknown
                    ? 'At least one platform did not confirm whether the post was published. Check the destination before retrying.'
                    : $post->targets->first(fn ($target): bool => $target->safe_error !== null)?->safe_error,
            ]);
        });
    }
}
