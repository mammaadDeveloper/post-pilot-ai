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
use Illuminate\Support\Facades\Storage;

class LinkedInPublisher implements SocialPublisher
{
    use HandlesProviderErrors;

    private const string BASE_URL = 'https://api.linkedin.com';

    public function requiresPkce(): bool
    {
        return false;
    }

    public function authorizationUrl(string $state, string $redirectUri, ?string $codeChallenge = null): string
    {
        $config = config('social-publishing.providers.linkedin');
        $this->requireConfig($config, 'client_id', 'client_secret', 'api_version');
        if (! is_string($config['api_version']) || ! preg_match('/^20\d{4}$/', $config['api_version'])) {
            throw new PublicationException('Set LINKEDIN_API_VERSION to a currently supported YYYYMM version.', PublicationFailureType::Permanent);
        }
        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'openid profile w_member_social',
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://www.linkedin.com/oauth/v2/authorization?'.$query;
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        $config = config('social-publishing.providers.linkedin');
        $this->requireConfig($config, 'client_id', 'client_secret');
        $response = $this->executeOAuthRequest(fn () => Http::asForm()
            ->connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->post('https://www.linkedin.com/oauth/v2/accessToken', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'redirect_uri' => $redirectUri,
            ]));

        $data = $response->json();
        $this->requireAccessToken($data);

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            'scopes' => preg_split('/[ ,]+/', (string) ($data['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ];
    }

    public function fetchProfile(string $accessToken): array
    {
        $response = $this->executeOAuthRequest(fn () => Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->get(self::BASE_URL.'/v2/userinfo'));

        $data = $response->json();

        if (! is_array($data) || ! is_string($data['sub'] ?? null) || $data['sub'] === '') {
            throw new PublicationException('LinkedIn did not return a usable member profile.', PublicationFailureType::Permanent);
        }

        return [
            'remote_account_id' => $data['sub'],
            'account_name' => $data['name'] ?? $data['given_name'] ?? null,
            'username' => $data['preferred_username'] ?? null,
            'metadata' => [],
        ];
    }

    public function refreshAccessToken(SocialAccount $account): ?array
    {
        return null;
    }

    public function publish(ScheduledPost $post, SocialAccount $account, ?string $imageUrl = null): PublishedPostResult
    {
        $version = config('social-publishing.providers.linkedin.api_version');

        if (! is_string($version) || ! preg_match('/^20\d{4}$/', $version)) {
            throw new PublicationException('Set LINKEDIN_API_VERSION to a currently supported YYYYMM version.', PublicationFailureType::Permanent);
        }

        $headers = [
            'LinkedIn-Version' => $version,
            'X-Restli-Protocol-Version' => '2.0.0',
        ];
        $content = [];

        if ($post->media_path !== null) {
            $upload = $this->executePublication(fn () => Http::withToken($account->access_token)
                ->acceptJson()
                ->withHeaders($headers)
                ->connectTimeout($this->publicationConnectTimeout())
                ->timeout($this->publicationRequestTimeout())
                ->post(self::BASE_URL.'/rest/images?action=initializeUpload', [
                    'initializeUploadRequest' => [
                        'owner' => 'urn:li:person:'.$account->remote_account_id,
                    ],
                ]));
            $uploadUrl = $upload->json('value.uploadUrl');
            $imageUrn = $upload->json('value.image');

            if (! is_string($uploadUrl) || ! is_string($imageUrn)) {
                throw new PublicationException('LinkedIn did not initialize the image upload.', PublicationFailureType::Unknown);
            }

            $disk = Storage::disk($post->media_disk);
            $bytes = $disk->get($post->media_path);

            if (! is_string($bytes)) {
                throw new PublicationException('The scheduled image is no longer available.', PublicationFailureType::Permanent);
            }

            $this->executePublication(fn () => Http::withToken($account->access_token)
                ->withBody($bytes, $post->media_mime_type ?? 'application/octet-stream')
                ->connectTimeout($this->publicationConnectTimeout())
                ->timeout($this->publicationRequestTimeout())
                ->put($uploadUrl));
            $content = [
                'content' => [
                    'media' => [
                        'title' => $post->media_file_name ?? 'Post image',
                        'id' => $imageUrn,
                    ],
                ],
            ];
        }

        $body = [
            'author' => 'urn:li:person:'.$account->remote_account_id,
            'commentary' => $post->content,
            'visibility' => 'PUBLIC',
            'distribution' => [
                'feedDistribution' => 'MAIN_FEED',
                'targetEntities' => [],
                'thirdPartyDistributionChannels' => [],
            ],
            'lifecycleState' => 'PUBLISHED',
            'isReshareDisabledByAuthor' => false,
        ] + $content;
        $response = $this->executePublication(fn () => Http::withToken($account->access_token)
            ->acceptJson()
            ->withHeaders($headers)
            ->connectTimeout($this->publicationConnectTimeout())
            ->timeout($this->publicationRequestTimeout())
            ->post(self::BASE_URL.'/rest/posts', $body));
        $remotePostId = $response->header('x-restli-id');

        if ($remotePostId === '') {
            throw new PublicationException('LinkedIn accepted the publication request but did not confirm its post ID.', PublicationFailureType::Unknown);
        }

        return new PublishedPostResult($remotePostId);
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    private function requireConfig(array $providerConfig, string ...$keys): void
    {
        foreach ($keys as $key) {
            if (! is_string($providerConfig[$key] ?? null) || blank($providerConfig[$key])) {
                throw new PublicationException('Configure the LinkedIn OAuth client in the server environment.', PublicationFailureType::Permanent);
            }
        }
    }

    private function requireAccessToken(mixed $data): void
    {
        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new PublicationException('LinkedIn did not return an access token.', PublicationFailureType::Permanent);
        }
    }
}
