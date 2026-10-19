<?php

namespace App\Services\SocialPublishers;

use App\Contracts\SocialPublisher;
use App\Data\PublishedPostResult;
use App\Enums\PublicationFailureType;
use App\Exceptions\PublicationException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\SocialPublishers\Concerns\HandlesProviderErrors;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

class InstagramPublisher implements SocialPublisher
{
    use HandlesProviderErrors;

    private const string GRAPH_URL = 'https://graph.instagram.com';

    public function requiresPkce(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state, string $redirectUri, ?string $codeChallenge = null): string
    {
        $config = config('social-publishing.providers.instagram');
        $this->requireConfig($config, 'app_id', 'api_version');
        $query = http_build_query([
            'client_id' => $config['app_id'],
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'instagram_business_basic,instagram_business_content_publish',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://www.instagram.com/oauth/authorize?'.$query;
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $config = config('social-publishing.providers.instagram');
        $this->requireConfig($config, 'app_id', 'app_secret');
        $response = $this->executeOAuthRequest(fn () => Http::asMultipart()
            ->connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->post('https://api.instagram.com/oauth/access_token', [
                'client_id' => $config['app_id'],
                'client_secret' => $config['app_secret'],
                'grant_type' => 'authorization_code',
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ]));
        $shortLivedToken = $response->json('access_token');

        if (! is_string($shortLivedToken) || $shortLivedToken === '') {
            throw new PublicationException('Instagram did not return an access token.', PublicationFailureType::Permanent);
        }

        $longLivedResponse = $this->executeOAuthRequest(fn () => Http::connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->get(self::GRAPH_URL.'/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => $config['app_secret'],
                'access_token' => $shortLivedToken,
            ]));
        $data = $longLivedResponse->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new PublicationException('Instagram did not return a long-lived access token.', PublicationFailureType::Permanent);
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => null,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            'scopes' => ['instagram_business_basic', 'instagram_business_content_publish'],
        ];
    }

    public function fetchProfile(string $accessToken): array
    {
        $response = $this->executeOAuthRequest(fn () => Http::connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->get($this->graphUrl('/me'), [
                'fields' => 'user_id,username',
                'access_token' => $accessToken,
            ]));
        $data = $response->json();
        $remoteAccountId = is_array($data) ? ($data['user_id'] ?? $data['id'] ?? null) : null;

        if (! is_string($remoteAccountId) || $remoteAccountId === '') {
            throw new PublicationException('Instagram did not return a usable professional account profile.', PublicationFailureType::Permanent);
        }

        return [
            'remote_account_id' => $remoteAccountId,
            'account_name' => is_string($data['username'] ?? null) ? $data['username'] : null,
            'username' => is_string($data['username'] ?? null) ? $data['username'] : null,
            'metadata' => [],
        ];
    }

    public function refreshAccessToken(SocialAccount $account): ?array
    {
        if (blank($account->access_token) || $account->token_expires_at?->isPast()) {
            return null;
        }

        $response = $this->executeOAuthRequest(fn () => Http::connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->get(self::GRAPH_URL.'/refresh_access_token', [
                'grant_type' => 'ig_refresh_token',
                'access_token' => $account->access_token,
            ]));
        $data = $response->json();

        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new PublicationException('Instagram could not refresh this account token.', PublicationFailureType::Reauthorization);
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => null,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        ];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, ?string $imageUrl = null): PublishedPostResult
    {
        if ($post->media_path === null || ! is_string($imageUrl) || $imageUrl === '') {
            throw new PublicationException('Instagram publishing requires an image that the platform can securely fetch.', PublicationFailureType::Permanent);
        }

        $container = $this->executePublication(fn () => Http::withToken($account->access_token)
            ->acceptJson()
            ->connectTimeout($this->publicationConnectTimeout())
            ->timeout($this->publicationRequestTimeout())
            ->post($this->graphUrl('/'.$account->remote_account_id.'/media'), [
                'image_url' => $imageUrl,
                'caption' => $post->content,
            ]));
        $containerId = $container->json('id');

        if (! is_string($containerId) || $containerId === '') {
            throw new PublicationException('Instagram did not create a media container.', PublicationFailureType::Unknown);
        }

        $this->waitForContainer($account->access_token, $containerId);

        $published = $this->executePublication(fn () => Http::withToken($account->access_token)
            ->acceptJson()
            ->connectTimeout(min(3, $this->publicationConnectTimeout()))
            ->timeout(min(6, $this->publicationRequestTimeout()))
            ->post($this->graphUrl('/'.$account->remote_account_id.'/media_publish'), [
                'creation_id' => $containerId,
            ]));
        $remotePostId = $published->json('id');

        if (! is_string($remotePostId) || $remotePostId === '') {
            throw new PublicationException('Instagram accepted the publication request but did not confirm its post ID.', PublicationFailureType::Unknown);
        }

        return new PublishedPostResult($remotePostId);
    }

    private function waitForContainer(string $accessToken, string $containerId): void
    {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $response = $this->executePublication(fn () => Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout((int) config('social-publishing.connection_timeout'))
                ->timeout((int) config('social-publishing.request_timeout'))
                ->get($this->graphUrl('/'.$containerId), [
                    'fields' => 'status_code',
                ]));
            $status = $response->json('status_code');

            if ($status === 'FINISHED') {
                return;
            }

            if (in_array($status, ['ERROR', 'EXPIRED'], true)) {
                throw new PublicationException('Instagram could not process the scheduled image.', PublicationFailureType::Permanent);
            }

            Sleep::for(2)->seconds();
        }

        throw new PublicationException(
            'Instagram is still processing the scheduled image; the post was not submitted.',
            PublicationFailureType::Transient,
            30,
        );
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    private function requireConfig(array $providerConfig, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (! is_string($providerConfig[$key] ?? null) || blank($providerConfig[$key])) {
                throw new PublicationException('Configure the Instagram OAuth client in the server environment.', PublicationFailureType::Permanent);
            }
        }
    }

    private function graphUrl(string $path): string
    {
        $version = config('social-publishing.providers.instagram.api_version');

        if (! is_string($version) || ! preg_match('/^v\d+\.0$/', $version)) {
            throw new PublicationException('Set INSTAGRAM_GRAPH_API_VERSION to a currently supported Graph API version.', PublicationFailureType::Permanent);
        }

        return self::GRAPH_URL.'/'.$version.$path;
    }
}
