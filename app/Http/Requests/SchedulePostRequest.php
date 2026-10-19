<?php

namespace App\Http\Requests;

use App\Enums\SocialPlatform;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class SchedulePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_key' => ['required', 'uuid', 'max:64'],
            'content' => ['required', 'string', 'max:5000'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'timezone' => ['required', 'string', Rule::in(timezone_identifiers_list())],
            'account_ids' => ['required', 'array', 'min:1', 'max:10'],
            'account_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('social_accounts', 'id')
                    ->where('user_id', $this->user()?->getAuthIdentifier())
                    ->where('status', 'connected')
                    ->whereNull('disconnected_at'),
            ],
            'media_data_url' => [
                'nullable',
                'string',
                'max:1500000',
                'regex:/^data:image\\/(?:jpeg|png);base64,[A-Za-z0-9+\\/=]+$/',
            ],
            'media_file_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array{request_key: string, content: string, scheduled_at: string, timezone: string, account_ids: array<int, int|string>, media_data_url: string|null, media_file_name: string|null}
     */
    public function scheduledPostData(): array
    {
        $validated = $this->validated();
        $accountIds = $validated['account_ids'] ?? null;

        if (! is_array($accountIds)) {
            throw new LogicException('Validated publication accounts must be an array.');
        }

        $accountIds = array_map(static function (mixed $accountId): int|string {
            if (! is_int($accountId) && ! is_string($accountId)) {
                throw new LogicException('Validated publication account IDs must be integers or strings.');
            }

            return $accountId;
        }, $accountIds);

        $mediaDataUrl = $validated['media_data_url'] ?? null;
        $mediaFileName = $validated['media_file_name'] ?? null;

        if (($mediaDataUrl !== null && ! is_string($mediaDataUrl))
            || ($mediaFileName !== null && ! is_string($mediaFileName))) {
            throw new LogicException('Validated publication media fields must be strings or null.');
        }

        return [
            'request_key' => $this->validatedString($validated, 'request_key'),
            'content' => $this->validatedString($validated, 'content'),
            'scheduled_at' => $this->validatedString($validated, 'scheduled_at'),
            'timezone' => $this->validatedString($validated, 'timezone'),
            'account_ids' => $accountIds,
            'media_data_url' => $mediaDataUrl,
            'media_file_name' => $mediaFileName,
        ];
    }

    /**
     * Validate content limits for each selected platform.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->count() > 0) {
                    return;
                }

                $accountIds = array_map('intval', $this->input('account_ids', []));
                $accounts = $this->user()->socialAccounts()->whereKey($accountIds)->get();
                $length = mb_strlen($this->string('content')->toString());
                $platforms = $accounts->pluck('platform')->unique();

                foreach ($platforms as $platform) {
                    $limit = match ($platform) {
                        SocialPlatform::X => 280,
                        SocialPlatform::Instagram => 2200,
                        default => 3000,
                    };

                    if ($length > $limit) {
                        $validator->errors()->add('content', "Content exceeds the {$platform->value} limit of {$limit} characters.");
                        break;
                    }

                }

                if ($platforms->contains(SocialPlatform::Instagram) && blank($this->input('media_data_url'))) {
                    $validator->errors()->add('media_data_url', 'Instagram publication requires an image.');
                }

                if ($platforms->contains(SocialPlatform::Instagram)
                    && is_string($this->input('media_data_url'))
                    && ! str_starts_with($this->input('media_data_url'), 'data:image/jpeg;base64,')) {
                    $validator->errors()->add('media_data_url', 'Instagram requires a JPEG image.');
                }
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validatedString(array $validated, string $key): string
    {
        $value = $validated[$key] ?? null;

        if (! is_string($value)) {
            throw new LogicException("Validated publication field [{$key}] must be a string.");
        }

        return $value;
    }
}
