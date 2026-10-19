<?php

use App\Enums\PublicationTargetStatus;
use App\Enums\SocialPlatform;
use App\Jobs\PublishPublicationTarget;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\RefreshScheduledPostStatus;
use App\Services\SocialPublisherManager;
use App\Services\SocialPublishers\InstagramPublisher;
use App\Services\SocialPublishers\LinkedInPublisher;
use App\Services\SocialPublishers\XPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('LinkedIn publishes text with its configured API version', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::LinkedIn,
        'remote_account_id' => 'member-123',
        'access_token' => 'linkedin-access-token',
    ]);
    $post = ScheduledPost::factory()->for($user)->create([
        'content' => 'LinkedIn post content.',
    ]);

    config(['social-publishing.providers.linkedin.api_version' => '202610']);
    Http::preventStrayRequests();
    Http::fake([
        'api.linkedin.com/rest/posts' => Http::response([], 201, [
            'x-restli-id' => 'urn:li:share:456',
        ]),
    ]);

    $result = app(LinkedInPublisher::class)->publish($post, $account);

    expect($result->remotePostId)->toBe('urn:li:share:456');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.linkedin.com/rest/posts'
        && $request->hasHeader('Authorization', 'Bearer linkedin-access-token')
        && $request->hasHeader('LinkedIn-Version', '202610')
        && $request['author'] === 'urn:li:person:member-123'
        && $request['commentary'] === 'LinkedIn post content.');
});

test('LinkedIn uploads an image before creating an image post', function () {
    $user = User::factory()->create();
    Storage::fake('local');
    Storage::disk('local')->put('scheduled-publications/image.jpg', 'image-bytes');
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::LinkedIn,
        'remote_account_id' => 'member-123',
        'access_token' => 'linkedin-access-token',
    ]);
    $post = ScheduledPost::factory()->for($user)->create([
        'media_disk' => 'local',
        'media_path' => 'scheduled-publications/image.jpg',
        'media_mime_type' => 'image/jpeg',
        'media_file_name' => 'photo.jpg',
    ]);

    config(['social-publishing.providers.linkedin.api_version' => '202610']);
    Http::preventStrayRequests();
    Http::fake([
        'api.linkedin.com/rest/images*' => Http::response([
            'value' => [
                'uploadUrl' => 'https://www.linkedin.com/dms-uploads/upload-1',
                'image' => 'urn:li:image:789',
            ],
        ]),
        'www.linkedin.com/dms-uploads/upload-1' => Http::response([], 201),
        'api.linkedin.com/rest/posts' => Http::response([], 201, [
            'x-restli-id' => 'urn:li:share:456',
        ]),
    ]);

    $result = app(LinkedInPublisher::class)->publish($post, $account);

    expect($result->remotePostId)->toBe('urn:li:share:456');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.linkedin.com/dms-uploads/upload-1'
        && $request->method() === 'PUT'
        && $request->hasHeader('Authorization', 'Bearer linkedin-access-token'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.linkedin.com/rest/posts'
        && $request['content']['media']['id'] === 'urn:li:image:789');
});

test('Instagram waits for its image container before publishing', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::Instagram,
        'remote_account_id' => 'ig-user-123',
        'access_token' => 'instagram-access-token',
    ]);
    $post = ScheduledPost::factory()->for($user)->create([
        'media_disk' => 'local',
        'media_path' => 'scheduled-publications/image.jpg',
        'media_mime_type' => 'image/jpeg',
    ]);

    config(['social-publishing.providers.instagram.api_version' => 'v26.0']);
    Http::preventStrayRequests();
    Http::fake([
        'graph.instagram.com/v26.0/ig-user-123/media' => Http::response(['id' => 'container-123']),
        'graph.instagram.com/v26.0/container-123*' => Http::response(['status_code' => 'FINISHED']),
        'graph.instagram.com/v26.0/ig-user-123/media_publish' => Http::response(['id' => 'ig-post-456']),
    ]);

    $result = app(InstagramPublisher::class)->publish(
        $post,
        $account,
        'https://postpilot.example/publication-media/1?signature=signed',
    );

    expect($result->remotePostId)->toBe('ig-post-456');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.instagram.com/v26.0/ig-user-123/media'
        && $request->hasHeader('Authorization', 'Bearer instagram-access-token')
        && $request['image_url'] === 'https://postpilot.example/publication-media/1?signature=signed');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/container-123')
        && $request['fields'] === 'status_code');
});

test('X uploads and processes an image before creating a media post', function () {
    $user = User::factory()->create();
    Storage::fake('local');
    Storage::disk('local')->put('scheduled-publications/image.jpg', 'image-bytes');
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::X,
        'access_token' => 'x-access-token',
    ]);
    $post = ScheduledPost::factory()->for($user)->create([
        'media_disk' => 'local',
        'media_path' => 'scheduled-publications/image.jpg',
        'media_file_name' => 'photo.jpg',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'api.x.com/2/media/upload*' => fn (Request $request) => $request->method() === 'POST'
            ? Http::response(['data' => ['id' => 'media-123']])
            : Http::response(['data' => ['processing_info' => ['state' => 'succeeded']]]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'x-post-456']], 201),
    ]);

    $result = app(XPublisher::class)->publish($post, $account);

    expect($result->remotePostId)->toBe('x-post-456');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/media/upload'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer x-access-token')
        && str_contains($request->body(), 'name="media_category"')
        && str_contains($request->body(), 'tweet_image'));
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/tweets'
        && $request['media']['media_ids'] === ['media-123']);
});

test('LinkedIn OAuth state is single-use and tokens stay out of serialization', function () {
    $user = User::factory()->create();
    config([
        'social-publishing.providers.linkedin.client_id' => 'linkedin-client',
        'social-publishing.providers.linkedin.client_secret' => 'linkedin-secret',
        'social-publishing.providers.linkedin.api_version' => '202610',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'www.linkedin.com/oauth/v2/accessToken' => Http::response([
            'access_token' => 'linkedin-access-token',
            'expires_in' => 3600,
            'scope' => 'openid profile w_member_social',
        ]),
        'api.linkedin.com/v2/userinfo' => Http::response([
            'sub' => 'member-123',
            'name' => 'LinkedIn Member',
            'preferred_username' => 'member',
        ]),
    ]);

    $connectResponse = $this->actingAs($user)
        ->get(route('social-accounts.connect', ['platform' => 'linkedin']))
        ->assertRedirect();
    parse_str((string) parse_url($connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->get(route('social-accounts.callback', [
        'platform' => 'linkedin',
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]))->assertRedirect(route('schedule.index'));

    $this->get(route('social-accounts.callback', [
        'platform' => 'linkedin',
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]))->assertForbidden();

    $account = SocialAccount::query()->sole();
    expect($account->access_token)->toBe('linkedin-access-token')
        ->and($account->toArray())->not->toHaveKey('access_token')
        ->and($account->toArray())->not->toHaveKey('refresh_token');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://www.linkedin.com/oauth/v2/accessToken'
        && $request['client_secret'] === 'linkedin-secret'
        && $request['grant_type'] === 'authorization_code');
});

test('an invalid OAuth state cannot attach a social account', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    config([
        'social-publishing.providers.linkedin.client_id' => 'linkedin-client',
        'social-publishing.providers.linkedin.client_secret' => 'linkedin-secret',
        'social-publishing.providers.linkedin.api_version' => '202610',
    ]);
    $connectResponse = $this->actingAs($owner)
        ->get(route('social-accounts.connect', ['platform' => 'linkedin']))
        ->assertRedirect();
    parse_str((string) parse_url($connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);

    $this->actingAs($otherUser)
        ->get(route('social-accounts.callback', [
            'platform' => 'linkedin',
            'state' => $query['state'],
            'code' => 'authorization-code',
        ]))
        ->assertForbidden();

    $this->assertDatabaseCount('social_accounts', 0);
});

test('X OAuth uses PKCE and stores the account returned by the provider', function () {
    $user = User::factory()->create();
    config([
        'social-publishing.providers.x.client_id' => 'x-client',
        'social-publishing.providers.x.client_secret' => 'x-secret',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'x-access-token',
            'refresh_token' => 'x-refresh-token',
            'expires_in' => 7200,
            'scope' => 'tweet.read tweet.write users.read media.write offline.access',
        ]),
        'api.x.com/2/users/me*' => Http::response([
            'data' => [
                'id' => 'x-user-123',
                'name' => 'X User',
                'username' => 'xuser',
            ],
        ]),
    ]);

    $connectResponse = $this->actingAs($user)
        ->get(route('social-accounts.connect', ['platform' => 'x']))
        ->assertRedirect();
    parse_str((string) parse_url($connectResponse->headers->get('Location'), PHP_URL_QUERY), $query);

    expect($query['code_challenge_method'])->toBe('S256')
        ->and($query['code_challenge'])->not->toBeEmpty();

    $this->get(route('social-accounts.callback', [
        'platform' => 'x',
        'state' => $query['state'],
        'code' => 'authorization-code',
    ]))->assertRedirect(route('schedule.index'));

    $account = SocialAccount::query()->sole();
    expect($account->remote_account_id)->toBe('x-user-123')
        ->and($account->access_token)->toBe('x-access-token')
        ->and($account->refresh_token)->toBe('x-refresh-token');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/oauth2/token'
        && $request['grant_type'] === 'authorization_code'
        && is_string($request['code_verifier'] ?? null)
        && rtrim(strtr(base64_encode(hash('sha256', $request['code_verifier'], true)), '+/', '-_'), '=')
            === $query['code_challenge']);
});

test('X refreshes an expiring token before publishing', function () {
    $user = User::factory()->create();
    $account = SocialAccount::factory()->for($user)->create([
        'platform' => SocialPlatform::X,
        'access_token' => 'expired-soon-access-token',
        'refresh_token' => 'x-refresh-token',
        'token_expires_at' => now()->addDays(2),
    ]);
    $post = ScheduledPost::factory()->for($user)->create();
    $target = $post->targets()->create([
        'social_account_id' => $account->id,
        'status' => PublicationTargetStatus::Queued,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    config([
        'social-publishing.providers.x.client_id' => 'x-client',
        'social-publishing.providers.x.client_secret' => 'x-secret',
    ]);
    Http::preventStrayRequests();
    Http::fake([
        'api.x.com/2/oauth2/token' => Http::response([
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'rotated-refresh-token',
            'expires_in' => 7200,
        ]),
        'api.x.com/2/tweets' => Http::response(['data' => ['id' => 'x-post-789']], 201),
    ]);

    (new PublishPublicationTarget($target->id))->handle(
        app(SocialPublisherManager::class),
        app(RefreshScheduledPostStatus::class),
    );

    expect($account->fresh()->access_token)->toBe('refreshed-access-token')
        ->and($account->fresh()->refresh_token)->toBe('rotated-refresh-token')
        ->and($target->fresh()->remote_post_id)->toBe('x-post-789');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/oauth2/token'
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === 'x-refresh-token');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.x.com/2/tweets'
        && $request->hasHeader('Authorization', 'Bearer refreshed-access-token'));
});
