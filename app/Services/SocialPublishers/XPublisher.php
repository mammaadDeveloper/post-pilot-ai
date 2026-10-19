<?php

namespace App\Services\SocialPublishers;

use App\Contracts\SocialPublisher;
use App\Data\PublishedPostResult;
use App\Enums\PublicationFailureType;
use App\Exceptions\PublicationException;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;
use App\Services\SocialPublishers\Concerns\HandlesProviderErrors;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

class XPublisher implements SocialPublisher
{
    use HandlesProviderErrors;

    private const string API_URL = 'https://api.x.com/2';

    public function requiresPkce(): bool
    {
        return true;
    }

    public function authorizationUrl(string $state, string $redirectUri, ?string $codeChallenge = null): string
    {
        $config = config('social-publishing.providers.x');
        $this->requireConfig($config, 'client_id');

        if (! is_string($codeChallenge) || $codeChallenge === '') {
            throw new PublicationException('X authorization requires a PKCE challenge.', PublicationFailureType::Permanent);
        }

        return 'https://x.com/i/oauth2/authorize?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $config['client_id'],
            'redirect_uri' => $redirectUri,
            'scope' => 'tweet.read tweet.write users.read media.write offline.access',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function exchangeAuthorizationCode(string $code, string $redirectUri, ?string $codeVerifier = null): array
    {
        if (! is_string($codeVerifier) || $codeVerifier === '') {
            throw new PublicationException('X authorization is missing its PKCE verifier.', PublicationFailureType::Permanent);
        }

        $config = config('social-publishing.providers.x');
        $this->requireConfig($config, 'client_id');
        $response = $this->executeOAuthRequest(fn () => $this->tokenRequest($config)
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'code_verifier' => $codeVerifier,
                'client_id' => $config['client_id'],
            ]));
        $data = $response->json();
        $this->requireAccessToken($data);

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
            'scopes' => preg_split('/\s+/', (string) ($data['scope'] ?? ''), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ];
    }

    public function fetchProfile(string $accessToken): array
    {
        $response = $this->executeOAuthRequest(fn () => Http::withToken($accessToken)
            ->acceptJson()
            ->connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'))
            ->get(self::API_URL.'/users/me', [
                'user.fields' => 'id,name,username',
            ]));
        $data = $response->json('data');

        if (! is_array($data) || ! is_string($data['id'] ?? null) || $data['id'] === '') {
            throw new PublicationException('X did not return a usable account profile.', PublicationFailureType::Permanent);
        }

        return [
            'remote_account_id' => $data['id'],
            'account_name' => is_string($data['name'] ?? null) ? $data['name'] : null,
            'username' => is_string($data['username'] ?? null) ? $data['username'] : null,
            'metadata' => [],
        ];
    }

    public function refreshAccessToken(SocialAccount $account): ?array
    {
        if (blank($account->refresh_token)) {
            return null;
        }

        $config = config('social-publishing.providers.x');
        $this->requireConfig($config, 'client_id');
        $response = $this->executeOAuthRequest(fn () => $this->tokenRequest($config)
            ->post('https://api.x.com/2/oauth2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $account->refresh_token,
                'client_id' => $config['client_id'],
            ]));
        $data = $response->json();
        $this->requireAccessToken($data);

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? $account->refresh_token,
            'expires_in' => isset($data['expires_in']) ? (int) $data['expires_in'] : null,
        ];
    }

    public function publish(ScheduledPost $post, SocialAccount $account, ?string $imageUrl = null): PublishedPostResult
    {
        $mediaIds = [];

        if ($post->media_path !== null) {
            $disk = Storage::disk($post->media_disk);
            $bytes = $disk->get($post->media_path);

            if (! is_string($bytes)) {
                throw new PublicationException('The scheduled image is no longer available.', PublicationFailureType::Permanent);
            }

            if (strlen($bytes) > 5 * 1024 * 1024) {
                throw new PublicationException('X image uploads must be no larger than 5 MB.', PublicationFailureType::Permanent);
            }

            $fileName = basename($post->media_file_name ?? 'post-image.jpg');
            $upload = $this->executePublication(fn () => Http::withToken($account->access_token)
                ->acceptJson()
                ->attach('media', $bytes, $fileName)
                ->connectTimeout($this->publicationConnectTimeout())
                ->timeout($this->publicationRequestTimeout())
                ->post(self::API_URL.'/media/upload', [
                    'media_category' => 'tweet_image',
                ]));
            $mediaId = $upload->json('data.id');

            if (! is_string($mediaId) || $mediaId === '') {
                throw new PublicationException('X accepted the image upload but did not confirm its media ID.', PublicationFailureType::Unknown);
            }

            $this->waitForMediaProcessing($account->access_token, $mediaId);
            $mediaIds[] = $mediaId;
        }

        $body = ['text' => $post->content];

        if ($mediaIds !== []) {
            $body['media'] = ['media_ids' => $mediaIds];
        }

        $response = $this->executePublication(fn () => Http::withToken($account->access_token)
            ->acceptJson()
            ->connectTimeout($this->publicationConnectTimeout())
            ->timeout($this->publicationRequestTimeout())
            ->post(self::API_URL.'/tweets', $body));
        $remotePostId = $response->json('data.id');

        if (! is_string($remotePostId) || $remotePostId === '') {
            throw new PublicationException('X accepted the publication request but did not confirm its post ID.', PublicationFailureType::Unknown);
        }

        return new PublishedPostResult($remotePostId);
    }

    /**
     * @param  array<string, mixed>  $providerConfig
     */
    private function tokenRequest(array $providerConfig): PendingRequest
    {
        $request = Http::asForm()
            ->acceptJson()
            ->connectTimeout((int) config('social-publishing.connection_timeout'))
            ->timeout((int) config('social-publishing.request_timeout'));

        if (is_string($providerConfig['client_secret'] ?? null) && $providerConfig['client_secret'] !== '') {
            return $request->withBasicAuth($providerConfig['client_id'], $providerConfig['client_secret']);
        }

        return $request;
    }

    private function waitForMediaProcessing(string $accessToken, string $mediaId): void
    {
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $response = $this->executePublication(fn () => Http::withToken($accessToken)
                ->acceptJson()
                ->connectTimeout(min(3, $this->publicationConnectTimeout()))
                ->timeout(min(6, $this->publicationRequestTimeout()))
                ->get(self::API_URL.'/media/upload', [
                    'media_id' => $mediaId,
                    'command' => 'STATUS',
                ]));
            $processingInfo = $response->json('data.processing_info', []);
            $state = $processingInfo['state'] ?? 'succeeded';

            if ($state === 'succeeded') {
                return;
            }

            if ($state === 'failed') {
                throw new PublicationException('X could not process the uploaded image.', PublicationFailureType::Permanent);
            }

            $delay = is_numeric($processingInfo['check_after_secs'] ?? null)
                ? min(2, max(1, (int) $processingInfo['check_after_secs']))
                : 1;
            Sleep::for($delay)->seconds();
        }

        throw new PublicationException(
            'X is still processing the uploaded image; the post was not submitted.',
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
                throw new PublicationException('Configure the X OAuth client in the server environment.', PublicationFailureType::Permanent);
            }
        }
    }

    private function requireAccessToken(mixed $data): void
    {
        if (! is_array($data) || ! is_string($data['access_token'] ?? null) || $data['access_token'] === '') {
            throw new PublicationException('X did not return an access token.', PublicationFailureType::Permanent);
        }
    }
}
