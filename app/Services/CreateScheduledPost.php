<?php

namespace App\Services;

use App\Enums\PublicationTargetStatus;
use App\Enums\ScheduledPostStatus;
use App\Models\ScheduledPost;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateScheduledPost
{
    /**
     * @param  array{request_key: string, content: string, scheduled_at: string, timezone: string, account_ids: array<int, int|string>, media_data_url?: string|null, media_file_name?: string|null}  $input
     */
    public function handle(User $user, array $input): ScheduledPost
    {
        $existingPost = $user->scheduledPosts()
            ->where('idempotency_key', $input['request_key'])
            ->first();

        if ($existingPost !== null) {
            return $existingPost;
        }

        $accounts = $user->socialAccounts()
            ->whereIn('id', $input['account_ids'])
            ->where('status', 'connected')
            ->whereNull('disconnected_at')
            ->get();

        if ($accounts->count() !== count($input['account_ids'])) {
            throw ValidationException::withMessages([
                'account_ids' => 'Select connected accounts that belong to you.',
            ]);
        }

        $scheduledAt = CarbonImmutable::parse($input['scheduled_at'])->utc();
        $media = [];

        try {
            return DB::transaction(function () use ($user, $input, $accounts, &$media, $scheduledAt): ScheduledPost {
                $existingPost = $user->scheduledPosts()
                    ->where('idempotency_key', $input['request_key'])
                    ->first();

                if ($existingPost !== null) {
                    return $existingPost;
                }

                $media = $this->storeMedia($input['media_data_url'] ?? null, $input['media_file_name'] ?? null);
                $post = $user->scheduledPosts()->create([
                    'idempotency_key' => $input['request_key'],
                    'content' => $input['content'],
                    'media_disk' => $media['disk'] ?? null,
                    'media_path' => $media['path'] ?? null,
                    'media_mime_type' => $media['mime_type'] ?? null,
                    'media_file_name' => $media['file_name'] ?? null,
                    'scheduled_at' => $scheduledAt,
                    'timezone' => $input['timezone'],
                    'status' => ScheduledPostStatus::Pending,
                ]);

                foreach ($accounts as $account) {
                    $post->targets()->create([
                        'social_account_id' => $account->id,
                        'status' => PublicationTargetStatus::Pending,
                        'idempotency_key' => (string) Str::uuid(),
                    ]);
                }

                return $post->load('targets.socialAccount');
            });
        } catch (Throwable $exception) {
            if (isset($media['path'])) {
                Storage::disk($media['disk'])->delete($media['path']);
            }

            if ($exception instanceof QueryException) {
                $existingPost = $user->scheduledPosts()
                    ->where('idempotency_key', $input['request_key'])
                    ->first();

                if ($existingPost !== null) {
                    return $existingPost;
                }
            }

            throw $exception;
        }
    }

    /**
     * @return array{disk: string, path: string, mime_type: string, file_name: string|null}|array{}
     */
    private function storeMedia(?string $dataUrl, ?string $fileName): array
    {
        if ($dataUrl === null || $dataUrl === '') {
            return [];
        }

        if (! preg_match('/^data:image\\/(jpeg|png);base64,([A-Za-z0-9+\\/=]+)$/', $dataUrl, $matches)) {
            throw ValidationException::withMessages([
                'media_data_url' => 'Choose a valid JPEG or PNG image.',
            ]);
        }

        $binary = base64_decode($matches[2], true);
        $image = $binary === false ? false : @getimagesizefromstring($binary);
        $mimeType = $image['mime'] ?? null;
        $expectedMime = 'image/'.($matches[1] === 'jpeg' ? 'jpeg' : $matches[1]);

        if ($binary === false || $mimeType !== $expectedMime || strlen($binary) > 1_048_576
            || ($image[0] ?? 0) > 8000 || ($image[1] ?? 0) > 8000) {
            throw ValidationException::withMessages([
                'media_data_url' => 'The image must be valid and no larger than 1 MB.',
            ]);
        }

        $disk = (string) config('social-publishing.media_disk');
        $extension = $mimeType === 'image/jpeg' ? 'jpg' : 'png';
        $path = 'scheduled-publications/'.Str::uuid().'.'.$extension;

        if (! Storage::disk($disk)->put($path, $binary, ['visibility' => 'private'])) {
            throw new \RuntimeException('The scheduled post image could not be stored.');
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $mimeType,
            'file_name' => $fileName === null ? null : mb_substr(basename($fileName), 0, 255),
        ];
    }
}
