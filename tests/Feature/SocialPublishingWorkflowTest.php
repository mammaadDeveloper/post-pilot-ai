<?php

use App\Enums\PublicationTargetStatus;
use App\Enums\ScheduledPostStatus;
use App\Enums\SocialPlatform;
use App\Jobs\PublishPublicationTarget;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\RefreshScheduledPostStatus;
use App\Services\SocialPublisherManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function scheduledPublicationPayload(SocialAccount $account, array $overrides = []): array
{
    return array_merge([
        'request_key' => (string) Str::uuid(),
        'content' => 'A post ready to publish.',
        'scheduled_at' => now()->addHour()->toIso8601String(),
        'timezone' => 'UTC',
        'account_ids' => [$account->id],
    ], $overrides);
}

test('guests cannot view the publishing schedule', function () {
    $this->get(route('schedule.index'))->assertRedirect(route('login'));
});

test('users can schedule content for their connected account', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();

    $this->actingAs($user)
        ->post(route('schedule.store'), scheduledPublicationPayload($account))
        ->assertRedirect(route('schedule.index'));

    $this->assertDatabaseHas('scheduled_posts', [
        'user_id' => $user->id,
        'content' => 'A post ready to publish.',
        'status' => ScheduledPostStatus::Pending->value,
    ]);
    $this->assertDatabaseHas('publication_targets', [
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Pending->value,
    ]);
});

test('the schedule view only includes the signed-in user records', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $ownAccount = SocialAccount::factory()->for($user)->create();
    $otherAccount = SocialAccount::factory()->for($owner)->create();
    $ownPost = ScheduledPost::factory()->for($user)->create();
    $otherPost = ScheduledPost::factory()->for($owner)->create();
    $ownPost->targets()->create([
        'social_account_id' => $ownAccount->id,
        'status' => PublicationTargetStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $otherPost->targets()->create([
        'social_account_id' => $otherAccount->id,
        'status' => PublicationTargetStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($user)
        ->get(route('schedule.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('schedule/Index')
            ->has('scheduledPosts', 1)
            ->where('scheduledPosts.0.id', (string) $ownPost->id)
            ->has('socialAccounts', 1)
            ->where('socialAccounts.0.id', (string) $ownAccount->id));
});

test('users cannot schedule posts to another users account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $foreignAccount = SocialAccount::factory()->for($otherUser)->create();

    $this->actingAs($user)
        ->from(route('schedule.index'))
        ->post(route('schedule.store'), scheduledPublicationPayload($foreignAccount))
        ->assertRedirect(route('schedule.index'))
        ->assertSessionHasErrors('account_ids.0');

    $this->assertDatabaseCount('scheduled_posts', 0);
    $this->assertDatabaseCount('publication_targets', 0);
});

test('Instagram scheduling requires a JPEG image', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::Instagram,
    ]);

    $this->actingAs($user)
        ->from(route('schedule.index'))
        ->post(route('schedule.store'), scheduledPublicationPayload($account))
        ->assertRedirect(route('schedule.index'))
        ->assertSessionHasErrors([
            'media_data_url' => 'Instagram publication requires an image.',
        ]);
});

test('X content over its post limit is rejected', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::X,
    ]);

    $this->actingAs($user)
        ->from(route('schedule.index'))
        ->post(route('schedule.store'), scheduledPublicationPayload($account, [
            'content' => str_repeat('a', 281),
        ]))
        ->assertRedirect(route('schedule.index'))
        ->assertSessionHasErrors('content');

    $this->assertDatabaseCount('scheduled_posts', 0);
});

test('repeated schedule requests with one key create one publication', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $payload = scheduledPublicationPayload($account);

    $this->actingAs($user)->post(route('schedule.store'), $payload)->assertRedirect();
    $this->post(route('schedule.store'), $payload)->assertRedirect();

    $this->assertDatabaseCount('scheduled_posts', 1);
    $this->assertDatabaseCount('publication_targets', 1);
});

test('users cannot cancel another users scheduled post', function () {
    $user = User::factory()->create();
    $owner = User::factory()->create();
    $post = ScheduledPost::factory()->for($owner)->create();

    $this->actingAs($user)
        ->delete(route('schedule.cancel', $post))
        ->assertNotFound();

    expect($post->fresh()->status)->toBe(ScheduledPostStatus::Pending);
});

test('users can cancel a post before publication starts', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $post = ScheduledPost::factory()->for($user)->create();
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($user)
        ->delete(route('schedule.cancel', $post))
        ->assertRedirect(route('schedule.index'));

    expect($post->fresh()->status)->toBe(ScheduledPostStatus::Cancelled)
        ->and($target->fresh()->status)->toBe(PublicationTargetStatus::Cancelled);
});

test('users cannot cancel a post after publication has started', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $post = ScheduledPost::factory()->for($user)->create([
        'status' => ScheduledPostStatus::Publishing,
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Processing,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($user)
        ->delete(route('schedule.cancel', $post))
        ->assertConflict();

    expect($post->fresh()->status)->toBe(ScheduledPostStatus::Publishing)
        ->and($target->fresh()->status)->toBe(PublicationTargetStatus::Processing);
});

test('users can retry a transient failure for a connected account', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $post = ScheduledPost::factory()->for($user)->create([
        'status' => ScheduledPostStatus::Failed,
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Failed,
        'attempts' => 4,
        'failure_type' => 'transient',
        'safe_error' => 'Temporary provider failure.',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($user)
        ->post(route('schedule.targets.retry', $target))
        ->assertRedirect(route('schedule.index'));

    expect($target->fresh()->status)->toBe(PublicationTargetStatus::Pending)
        ->and($target->fresh()->attempts)->toBe(0)
        ->and($target->fresh()->safe_error)->toBeNull()
        ->and($post->fresh()->status)->toBe(ScheduledPostStatus::Pending);
});

test('users cannot retry a publication with an uncertain outcome', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $post = ScheduledPost::factory()->for($user)->create([
        'status' => ScheduledPostStatus::OutcomeUnknown,
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::OutcomeUnknown,
        'failure_type' => 'unknown',
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->actingAs($user)
        ->post(route('schedule.targets.retry', $target))
        ->assertConflict();

    expect($target->fresh()->status)->toBe(PublicationTargetStatus::OutcomeUnknown)
        ->and($post->fresh()->status)->toBe(ScheduledPostStatus::OutcomeUnknown);
});

test('signed publication media is streamed only while the target is processing', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    Storage::fake('local');
    Storage::disk('local')->put('scheduled-publications/photo.jpg', 'image-bytes');
    $post = ScheduledPost::factory()->for($user)->create([
        'media_disk' => 'local',
        'media_path' => 'scheduled-publications/photo.jpg',
        'media_mime_type' => 'image/jpeg',
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Processing,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    $signedUrl = URL::temporarySignedRoute(
        'publication-media.show',
        now()->addMinute(),
        ['target' => $target],
    );

    $this->get($signedUrl)
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg')
        ->assertStreamedContent('image-bytes');

    $target->update(['status' => PublicationTargetStatus::Pending]);

    $this->get($signedUrl)->assertNotFound();
});

test('unsigned publication media requests are forbidden', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $post = ScheduledPost::factory()->for($user)->create([
        'media_disk' => 'local',
        'media_path' => 'scheduled-publications/photo.jpg',
        'media_mime_type' => 'image/jpeg',
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Processing,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $this->get(route('publication-media.show', $target))->assertForbidden();
});

test('the due publisher dispatches eligible publication targets', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create();
    $post = ScheduledPost::factory()->for($user)->create([
        'scheduled_at' => now()->subMinute(),
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Pending,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    Queue::fake([PublishPublicationTarget::class]);

    $this->artisan('publications:dispatch-due')->assertSuccessful();

    expect($target->fresh()->status)->toBe(PublicationTargetStatus::Queued);
    Queue::assertPushed(PublishPublicationTarget::class, fn (PublishPublicationTarget $job): bool => $job->targetId === $target->id);
});

test('publishing a post records the confirmed X post ID', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::X,
    ]);
    $post = ScheduledPost::factory()->for($user)->create([
        'status' => ScheduledPostStatus::Pending,
    ]);
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Queued,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'x-post-123']], 201),
    ]);

    (new PublishPublicationTarget($target->id))->handle(
        app(SocialPublisherManager::class),
        app(RefreshScheduledPostStatus::class),
    );

    expect($target->fresh()->status)->toBe(PublicationTargetStatus::Published)
        ->and($target->fresh()->remote_post_id)->toBe('x-post-123')
        ->and($post->fresh()->status)->toBe(ScheduledPostStatus::Published);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.x.com/2/tweets'
        && $request->hasHeader('Authorization', 'Bearer '.$account->access_token)
        && $request['text'] === $post->content);
});

test('uncertain X responses require manual outcome review instead of reposting', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::X,
    ]);
    $post = ScheduledPost::factory()->for($user)->create();
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Queued,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.x.com/2/tweets' => Http::response(['errors' => [['message' => 'temporary server error']]], 500),
    ]);

    (new PublishPublicationTarget($target->id))->handle(
        app(SocialPublisherManager::class),
        app(RefreshScheduledPostStatus::class),
    );

    expect($target->fresh()->status)->toBe(PublicationTargetStatus::OutcomeUnknown)
        ->and($post->fresh()->status)->toBe(ScheduledPostStatus::OutcomeUnknown);
    Http::assertSentCount(1);
});
