<?php

namespace App\Http\Controllers;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use App\Exceptions\PublicationException;
use App\Models\SocialAccount;
use App\Services\SocialPublisherManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SocialAccountController extends Controller
{
    public function connect(Request $request, string $platform, SocialPublisherManager $publishers): RedirectResponse
    {
        $socialPlatform = SocialPlatform::tryFrom($platform);
        abort_if($socialPlatform === null, 404);

        $publisher = $publishers->for($socialPlatform);
        $state = Str::random(64);
        $codeVerifier = $publisher->requiresPkce() ? Str::random(96) : null;
        $codeChallenge = $codeVerifier === null
            ? null
            : rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
        $redirectUri = route('social-accounts.callback', ['platform' => $platform]);

        try {
            $authorizationUrl = $publisher->authorizationUrl($state, $redirectUri, $codeChallenge);
        } catch (PublicationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('schedule.index');
        }

        Cache::put('social-oauth:'.hash('sha256', $state), [
            'user_id' => $request->user()->getAuthIdentifier(),
            'platform' => $socialPlatform->value,
            'code_verifier' => $codeVerifier,
        ], now()->addMinutes(10));

        return redirect()->away($authorizationUrl);
    }

    public function callback(Request $request, string $platform, SocialPublisherManager $publishers): RedirectResponse
    {
        $socialPlatform = SocialPlatform::tryFrom($platform);
        abort_if($socialPlatform === null, 404);

        $state = $request->string('state')->toString();
        $oauth = $state === ''
            ? null
            : Cache::pull('social-oauth:'.hash('sha256', $state));

        if (! is_array($oauth)
            || ! hash_equals((string) ($oauth['platform'] ?? ''), $socialPlatform->value)
            || ! hash_equals((string) ($oauth['user_id'] ?? ''), (string) $request->user()->getAuthIdentifier())) {
            abort(403, 'The social account authorization request expired or could not be verified.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('The social platform did not authorize this account.')]);

            return to_route('schedule.index');
        }

        try {
            $publisher = $publishers->for($socialPlatform);
            $redirectUri = route('social-accounts.callback', ['platform' => $platform]);
            $tokens = $publisher->exchangeAuthorizationCode(
                $request->string('code')->toString(),
                $redirectUri,
                $oauth['code_verifier'] ?? null,
            );
            $profile = $publisher->fetchProfile($tokens['access_token']);
            $account = SocialAccount::query()->updateOrCreate(
                [
                    'user_id' => $request->user()->getAuthIdentifier(),
                    'platform' => $socialPlatform,
                    'remote_account_id' => $profile['remote_account_id'],
                ],
                [
                    'account_name' => $profile['account_name'],
                    'username' => $profile['username'],
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => isset($tokens['expires_in'])
                        ? now()->addSeconds($tokens['expires_in'])
                        : null,
                    'scopes' => $tokens['scopes'],
                    'metadata' => $profile['metadata'],
                    'status' => SocialAccountStatus::Connected,
                    'disconnected_at' => null,
                ],
            );

            Log::info('Social account connected.', [
                'social_account_id' => $account->id,
                'user_id' => $request->user()->getAuthIdentifier(),
                'platform' => $socialPlatform->value,
            ]);

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Social account connected.')]);

            return to_route('schedule.index');
        } catch (PublicationException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return to_route('schedule.index');
        } catch (\Throwable $exception) {
            Log::error('Social account connection failed.', [
                'user_id' => $request->user()->getAuthIdentifier(),
                'platform' => $socialPlatform->value,
                'exception' => $exception::class,
            ]);

            Inertia::flash('toast', ['type' => 'error', 'message' => __('The social account could not be connected. Check the provider setup and try again.')]);

            return to_route('schedule.index');
        }
    }

    public function disconnect(Request $request, SocialAccount $socialAccount): RedirectResponse
    {
        abort_unless($socialAccount->user_id === $request->user()->getAuthIdentifier(), 404);

        $socialAccount->update([
            'access_token' => null,
            'refresh_token' => null,
            'status' => SocialAccountStatus::Disconnected,
            'disconnected_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Social account disconnected.')]);

        return to_route('schedule.index');
    }
}
