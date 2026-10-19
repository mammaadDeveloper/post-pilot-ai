<?php

namespace App\Console\Commands;

use App\Enums\PublicationTargetStatus;
use App\Jobs\PublishPublicationTarget;
use App\Models\PublicationTarget;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DispatchDuePublications extends Command
{
    protected $signature = 'publications:dispatch-due {--limit=100}';

    protected $description = 'Queue due social publication targets.';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $staleBefore = now()->subMinutes((int) config('social-publishing.stale_processing_minutes', 10));
        $targetIds = PublicationTarget::query()
            ->where(function ($query) use ($staleBefore): void {
                $query->where('status', PublicationTargetStatus::Pending->value)
                    ->orWhere(function ($query) use ($staleBefore): void {
                        $query->where('status', PublicationTargetStatus::Queued->value)
                            ->where('dispatched_at', '<=', $staleBefore);
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('retry_at')->orWhere('retry_at', '<=', now());
            })
            ->whereHas('scheduledPost', fn ($query) => $query->where('scheduled_at', '<=', now()))
            ->whereHas('socialAccount', fn ($query) => $query
                ->where('status', 'connected')
                ->whereNull('disconnected_at'))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;

        foreach ($targetIds as $targetId) {
            $wasDispatched = DB::transaction(function () use ($targetId, $staleBefore): bool {
                $target = PublicationTarget::query()->whereKey($targetId)->lockForUpdate()->first();

                if ($target === null
                    || ($target->status !== PublicationTargetStatus::Pending
                        && ! ($target->status === PublicationTargetStatus::Queued
                            && $target->dispatched_at?->lessThanOrEqualTo($staleBefore)))
                    || ($target->retry_at !== null && $target->retry_at->isFuture())
                    || $target->scheduledPost()->where('scheduled_at', '>', now())->exists()
                    || $target->socialAccount()->where(fn ($query) => $query
                        ->where('status', '!=', 'connected')
                        ->orWhereNotNull('disconnected_at'))->exists()) {
                    return false;
                }

                $target->update([
                    'status' => PublicationTargetStatus::Queued,
                    'dispatched_at' => now(),
                ]);

                return true;
            });

            if ($wasDispatched) {
                PublishPublicationTarget::dispatch($targetId);
            }

            $dispatched += (int) $wasDispatched;
        }

        $this->info("Dispatched {$dispatched} publication target(s).");

        return self::SUCCESS;
    }
}
