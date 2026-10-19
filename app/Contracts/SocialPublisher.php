<?php

namespace App\Contracts;

use App\Data\PublishedPostResult;
use App\Models\ScheduledPost;
use App\Models\SocialAccount;

interface SocialPublisher
{
    public function requiresPkce(): bool;

    public function authorizationUrl(string $state, string $redirectUri, ?string $codeChallenge = null): string;

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null, scopes: array<int, string>}
     */
    public function exchangeAuthorizationCode(string $code, string $redirectUri, ?string $codeVerifier = null): array;

    /**
     * @return array{remote_account_id: string, account_name: string|null, username: string|null, metadata: array<string, mixed>}
     */
    public function fetchProfile(string $accessToken): array;

    /**
     * @return array{access_token: string, refresh_token: string|null, expires_in: int|null}|null
     */
    public function refreshAccessToken(SocialAccount $account): ?array;

    public function publish(ScheduledPost $post, SocialAccount $account, ?string $imageUrl = null): PublishedPostResult;
}
