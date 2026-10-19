<?php

namespace App\Jobs;

use App\Enums\PublicationFailureType;
use App\Enums\PublicationTargetStatus;
use App\Enums\ScheduledPostStatus;
use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Exceptions\PublicationException;
use App\Models\PublicationTarget;
use App\Services\RefreshScheduledPostStatus;
use App\Services\SocialPublisherManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Throwable;

class PublishPublicationTarget implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $timeout = 90;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $targetId) {}

    public function uniqueId(): string
    {
        return 'publication-target:'.$this->targetId;
    }

    /**
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('publication-target:'.$this->targetId))
                ->releaseAfter(20)
                ->expireAfter((int) config('social-publishing.processing_lock_seconds', 120)),
        ];
    }

    public function handle(SocialPublisherManager $publishers, RefreshScheduledPostStatus $refreshStatus): void
    {
        $target = PublicationTarget::query()
            ->with(['scheduledPost', 'socialAccount'])
            ->whereKey($this->targetId)
            ->first();

        if ($target === null || $target->status !== PublicationTargetStatus::Queued) {
            return;
        }

        $publisher = $publishers->for($target->socialAccount->platform);

        if ($target->socialAccount->status !== SocialAccountStatus::Connected
            || $target->socialAccount->disconnected_at !== null) {
            $this->finish(
                PublicationFailureType::Reauthorization,
                'Reconnect this social account before publishing.',
                null,
                $refreshStatus,
            );

            return;
        }

        $refreshLeadTimeDays = match ($target->socialAccount->platform) {
            SocialPlatform::Instagram, SocialPlatform::X => 7,
            default => 0,
        };

        if ($target->socialAccount->token_expires_at?->lessThanOrEqualTo(now()->addDays($refreshLeadTimeDays))) {
            try {
                $refreshed = $publisher->refreshAccessToken($target->socialAccount);
            } catch (PublicationException $exception) {
                $this->finish(
                    $exception->failureType,
                    $exception->getMessage(),
                    $exception->retryAfterSeconds,
                    $refreshStatus,
                );

                return;
            } catch (Throwable $exception) {
                Log::error('Unexpected social token refresh failure.', [
                    'publication_target_id' => $this->targetId,
                    'exception' => $exception::class,
                ]);
                $this->finish(
                    PublicationFailureType::Unknown,
                    'The platform response could not be confirmed. Check the destination before retrying.',
                    null,
                    $refreshStatus,
                );

                return;
            }

            if ($refreshed === null) {
                $target->socialAccount->update(['status' => SocialAccountStatus::RequiresReauthorization]);
                $this->finish(
                    PublicationFailureType::Reauthorization,
                    'Reconnect this social account before publishing.',
                    null,
                    $refreshStatus,
                );

                return;
            }

            $target->socialAccount->update([
                'access_token' => $refreshed['access_token'],
                'refresh_token' => $refreshed['refresh_token'] ?? $target->socialAccount->refresh_token,
                'token_expires_at' => isset($refreshed['expires_in'])
                    ? now()->addSeconds($refreshed['expires_in'])
                    : null,
            ]);
            $target->socialAccount->refresh();
        }

        $claimed = DB::transaction(function (): bool {
            $target = PublicationTarget::query()->whereKey($this->targetId)->lockForUpdate()->first();

            if ($target === null || $target->status !== PublicationTargetStatus::Queued) {
                return false;
            }

            $target->update([
                'status' => PublicationTargetStatus::Processing,
                'attempts' => $target->attempts + 1,
                'last_attempt_at' => now(),
                'safe_error' => null,
            ]);
            $target->scheduledPost()->update(['status' => ScheduledPostStatus::Publishing]);

            return true;
        });

        if (! $claimed) {
            return;
        }

        try {
            $mediaUrl = $target->scheduledPost->media_path === null
                ? null
                : URL::temporarySignedRoute(
                    'publication-media.show',
                    now()->addHour(),
                    ['target' => $target->id],
                );
            $result = $publisher->publish($target->scheduledPost, $target->socialAccount, $mediaUrl);

            DB::transaction(function () use ($result, $refreshStatus): void {
                $target = PublicationTarget::query()->whereKey($this->targetId)->lockForUpdate()->first();

                if ($target === null || $target->status !== PublicationTargetStatus::Processing) {
                    return;
                }

                $target->update([
                    'status' => PublicationTargetStatus::Published,
                    'remote_post_id' => $result->remotePostId,
                    'published_at' => now(),
                    'retry_at' => null,
                    'failure_type' => null,
                    'safe_error' => null,
                ]);

                $refreshStatus->handle($target->scheduled_post_id);
            });
        } catch (PublicationException $exception) {
            $this->recordFailure($exception->failureType, $exception->getMessage(), $exception->retryAfterSeconds, $refreshStatus);
        } catch (Throwable $exception) {
            Log::error('Unexpected social publication failure.', [
                'publication_target_id' => $this->targetId,
                'exception' => $exception::class,
            ]);

            $this->recordFailure(
                PublicationFailureType::Unknown,
                'The platform response could not be confirmed. Check the destination before retrying.',
                null,
                $refreshStatus,
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $target = PublicationTarget::query()->whereKey($this->targetId)->first();

        if ($target?->status === PublicationTargetStatus::Processing) {
            $this->recordFailure(
                PublicationFailureType::Unknown,
                'The platform response could not be confirmed. Check the destination before retrying.',
                null,
                app(RefreshScheduledPostStatus::class),
            );
        }
    }

    private function recordFailure(
        PublicationFailureType $failureType,
        string $message,
        ?int $retryAfterSeconds,
        RefreshScheduledPostStatus $refreshStatus,
    ): void {
        $target = PublicationTarget::query()
            ->with('socialAccount')
            ->whereKey($this->targetId)
            ->first();

        if ($target === null) {
            return;
        }

        if ($failureType === PublicationFailureType::Reauthorization) {
            $target->socialAccount->update(['status' => SocialAccountStatus::RequiresReauthorization]);
        }

        $maxAttempts = max(1, (int) config('social-publishing.max_attempts', 4));
        $transientExhausted = $failureType === PublicationFailureType::Transient
            && $target->attempts >= $maxAttempts;
        $status = match (true) {
            $failureType === PublicationFailureType::Unknown => PublicationTargetStatus::OutcomeUnknown,
            $failureType === PublicationFailureType::Reauthorization => PublicationTargetStatus::RequiresReauthorization,
            $failureType === PublicationFailureType::Transient && ! $transientExhausted => PublicationTargetStatus::Pending,
            default => PublicationTargetStatus::Failed,
        };
        $retryDelay = $retryAfterSeconds ?? min(3600, 60 * (2 ** max(0, $target->attempts - 1)));

        DB::transaction(function () use ($status, $failureType, $message, $retryDelay, $refreshStatus): void {
            $lockedTarget = PublicationTarget::query()->whereKey($this->targetId)->lockForUpdate()->first();

            if ($lockedTarget === null || $lockedTarget->status !== PublicationTargetStatus::Processing) {
                return;
            }

            $lockedTarget->update([
                'status' => $status,
                'failure_type' => $failureType->value,
                'safe_error' => $message,
                'retry_at' => $status === PublicationTargetStatus::Pending ? now()->addSeconds($retryDelay) : null,
            ]);

            $refreshStatus->handle($lockedTarget->scheduled_post_id);
        });
    }

    private function finish(
        PublicationFailureType $failureType,
        string $message,
        ?int $retryAfterSeconds,
        RefreshScheduledPostStatus $refreshStatus,
    ): void {
        DB::transaction(function () use ($failureType, $message, $retryAfterSeconds, $refreshStatus): void {
            $target = PublicationTarget::query()->whereKey($this->targetId)->lockForUpdate()->first();

            if ($target === null || $target->status !== PublicationTargetStatus::Queued) {
                return;
            }

            $attempts = $target->attempts + 1;
            $maxAttempts = max(1, (int) config('social-publishing.max_attempts', 4));
            $transientExhausted = $failureType === PublicationFailureType::Transient
                && $attempts >= $maxAttempts;
            $status = match (true) {
                $failureType === PublicationFailureType::Unknown => PublicationTargetStatus::OutcomeUnknown,
                $failureType === PublicationFailureType::Reauthorization => PublicationTargetStatus::RequiresReauthorization,
                $failureType === PublicationFailureType::Transient && ! $transientExhausted => PublicationTargetStatus::Pending,
                default => PublicationTargetStatus::Failed,
            };
            $retryDelay = $retryAfterSeconds ?? min(3600, 60 * (2 ** max(0, $attempts - 1)));

            if ($failureType === PublicationFailureType::Reauthorization) {
                $target->socialAccount()->update(['status' => SocialAccountStatus::RequiresReauthorization]);
            }

            $target->update([
                'status' => $status,
                'attempts' => $attempts,
                'last_attempt_at' => now(),
                'failure_type' => $failureType->value,
                'safe_error' => $message,
                'retry_at' => $status === PublicationTargetStatus::Pending ? now()->addSeconds($retryDelay) : null,
            ]);
            $refreshStatus->handle($target->scheduled_post_id);
        });
    }
}
