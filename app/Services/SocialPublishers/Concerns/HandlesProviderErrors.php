<?php

namespace App\Services\SocialPublishers\Concerns;

use App\Enums\PublicationFailureType;
use App\Exceptions\PublicationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

trait HandlesProviderErrors
{
    private function throwForUnsuccessfulResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $retryAfter = $response->header('Retry-After');
        $retryAfterSeconds = ctype_digit($retryAfter)
            ? min(3600, (int) $retryAfter)
            : null;
        $failureType = match (true) {
            in_array($status, [401, 403], true) => PublicationFailureType::Reauthorization,
            $status === 429 => PublicationFailureType::Transient,
            $status >= 500 => PublicationFailureType::Unknown,
            default => PublicationFailureType::Permanent,
        };
        $message = match ($failureType) {
            PublicationFailureType::Reauthorization => 'This social account needs to be connected again.',
            PublicationFailureType::Transient => 'The social platform is temporarily rate limiting requests.',
            PublicationFailureType::Unknown => 'The social platform response was inconclusive; verify the account before retrying.',
            PublicationFailureType::Permanent => 'The social platform rejected this content or request.',
        };

        throw new PublicationException($message, $failureType, $retryAfterSeconds);
    }

    private function classifyConnectionFailure(ConnectionException $exception): never
    {
        throw new PublicationException(
            'The connection ended before publication could be confirmed. Verify the account before retrying.',
            PublicationFailureType::Unknown,
            previous: $exception,
        );
    }

    private function executePublication(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            $this->classifyConnectionFailure($exception);
        }

        $this->throwForUnsuccessfulResponse($response);

        return $response;
    }

    private function publicationConnectTimeout(): int
    {
        return min(3, max(1, (int) config('social-publishing.connection_timeout', 5)));
    }

    private function publicationRequestTimeout(): int
    {
        return min(15, max(1, (int) config('social-publishing.request_timeout', 45)));
    }

    private function executeOAuthRequest(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $exception) {
            throw new PublicationException(
                'The social platform could not be reached while connecting the account. Please try again.',
                PublicationFailureType::Transient,
                previous: $exception,
            );
        }

        $this->throwForOAuthResponse($response);

        return $response;
    }

    private function throwForOAuthResponse(Response $response): void
    {
        $this->throwForUnsuccessfulResponse($response);
    }
}
