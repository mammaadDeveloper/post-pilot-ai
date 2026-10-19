<?php

namespace App\Http\Controllers;

use App\Enums\PublicationTargetStatus;
use App\Enums\ScheduledPostStatus;
use App\Http\Requests\SchedulePostRequest;
use App\Models\PublicationTarget;
use App\Models\ScheduledPost;
use App\Services\CreateScheduledPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ScheduleController extends Controller
{
    /**
     * Show the schedule overview.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('schedule/Index', [
            'scheduledPosts' => $user->scheduledPosts()
                ->with('targets.socialAccount:id,platform,account_name,username,status')
                ->orderBy('scheduled_at')
                ->get()
                ->map(fn (ScheduledPost $post): array => [
                    'id' => (string) $post->id,
                    'content' => $post->content,
                    'scheduledAt' => $post->scheduled_at->toIso8601String(),
                    'timezone' => $post->timezone,
                    'status' => $post->status->value,
                    'targets' => $post->targets->map(fn (PublicationTarget $target): array => [
                        'id' => (string) $target->id,
                        'platform' => $target->socialAccount->platform->value,
                        'accountName' => $target->socialAccount->account_name,
                        'username' => $target->socialAccount->username,
                        'accountStatus' => $target->socialAccount->status->value,
                        'status' => $target->status->value,
                        'safeError' => $target->safe_error,
                        'remotePostId' => $target->remote_post_id,
                        'publishedAt' => $target->published_at?->toIso8601String(),
                        'retryable' => $target->socialAccount->status->value === 'connected'
                            && $target->socialAccount->disconnected_at === null
                            && (($target->status === PublicationTargetStatus::Failed
                                && $target->failure_type === 'transient')
                                || $target->status === PublicationTargetStatus::RequiresReauthorization),
                    ])->all(),
                ])->all(),
            'socialAccounts' => $user->socialAccounts()
                ->orderBy('platform')
                ->orderBy('account_name')
                ->get(['id', 'platform', 'account_name', 'username', 'status', 'disconnected_at'])
                ->map(fn ($account): array => [
                    'id' => (string) $account->id,
                    'platform' => $account->platform->value,
                    'accountName' => $account->account_name,
                    'username' => $account->username,
                    'status' => $account->status->value,
                    'disconnectedAt' => $account->disconnected_at?->toIso8601String(),
                ])->all(),
        ]);
    }

    public function store(SchedulePostRequest $request, CreateScheduledPost $create): RedirectResponse
    {
        $create->handle($request->user(), $request->scheduledPostData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post scheduled.')]);

        return to_route('schedule.index');
    }

    public function cancel(Request $request, ScheduledPost $scheduledPost): RedirectResponse
    {
        abort_unless($scheduledPost->user_id === $request->user()->id, 404);

        DB::transaction(function () use ($scheduledPost): void {
            $post = ScheduledPost::query()->lockForUpdate()->findOrFail($scheduledPost->id);
            $targets = $post->targets()->lockForUpdate()->get();

            if ($targets->contains(fn (PublicationTarget $target): bool => ! in_array(
                $target->status,
                [PublicationTargetStatus::Pending, PublicationTargetStatus::Queued],
                true,
            ))) {
                throw new HttpException(409, 'Only publications that have not started can be cancelled.');
            }

            $post->targets()->whereIn('status', [
                PublicationTargetStatus::Pending->value,
                PublicationTargetStatus::Queued->value,
            ])->update([
                'status' => PublicationTargetStatus::Cancelled->value,
                'updated_at' => now(),
            ]);

            $post->update(['status' => ScheduledPostStatus::Cancelled]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scheduled post cancelled.')]);

        return to_route('schedule.index');
    }

    public function retry(Request $request, PublicationTarget $target): RedirectResponse
    {
        abort_unless($target->scheduledPost()->where('user_id', $request->user()->id)->exists(), 404);

        DB::transaction(function () use ($target): void {
            $lockedTarget = PublicationTarget::query()->lockForUpdate()->with('socialAccount')->findOrFail($target->id);
            $accountIsConnected = $lockedTarget->socialAccount->status->value === 'connected'
                && $lockedTarget->socialAccount->disconnected_at === null;
            $isTransientFailure = $accountIsConnected
                && $lockedTarget->status === PublicationTargetStatus::Failed
                && $lockedTarget->failure_type === 'transient';
            $reauthorized = $accountIsConnected
                && $lockedTarget->status === PublicationTargetStatus::RequiresReauthorization;

            if (! $isTransientFailure && ! $reauthorized) {
                throw new HttpException(409, 'This publication cannot be safely retried.');
            }

            $lockedTarget->update([
                'status' => PublicationTargetStatus::Pending,
                'attempts' => 0,
                'failure_type' => null,
                'safe_error' => null,
                'retry_at' => null,
            ]);
            $lockedTarget->scheduledPost()->update([
                'status' => ScheduledPostStatus::Pending,
                'safe_error' => null,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Publication queued for retry.')]);

        return to_route('schedule.index');
    }
}
