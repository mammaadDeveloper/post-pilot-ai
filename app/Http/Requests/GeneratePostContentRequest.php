<?php

namespace App\Http\Requests;

use App\PostContentOperation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class GeneratePostContentRequest extends FormRequest
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
            'operation' => ['required', Rule::enum(PostContentOperation::class)],
            'topic' => ['nullable', 'string', 'max:'.config('postpilot.max_input_chars')],
            'content' => ['nullable', 'string', 'max:'.config('postpilot.max_input_chars')],
            'platform' => ['required', Rule::in(['LinkedIn', 'Instagram', 'X'])],
            'format' => ['nullable', 'string', 'max:80'],
            'tone' => ['required', 'string', 'in:Professional,Friendly,Educational,Casual,Persuasive'],
            'language' => ['required', 'string', 'in:English,Persian'],
            'audience' => ['nullable', 'string', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:'.config('postpilot.max_input_chars')],
            'variants' => ['sometimes', 'integer', 'min:1', 'max:'.min(3, (int) config('postpilot.max_variants'))],
        ];
    }

    /**
     * Validate fields whose requirements depend on the selected operation.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('operation')) {
                    return;
                }

                $operation = PostContentOperation::from($this->string('operation')->toString());

                if ($operation === PostContentOperation::Generate && blank($this->input('topic'))) {
                    $validator->errors()->add('topic', 'Add a topic to generate post content.');
                }

                if ($operation->requiresContent() && blank($this->input('content'))) {
                    $validator->errors()->add('content', 'Add existing post content for this operation.');
                }

                if ($operation === PostContentOperation::CaptionHashtags
                    && blank($this->input('content'))
                    && blank($this->input('topic'))) {
                    $validator->errors()->add('topic', 'Add a topic or post content for a caption and hashtags.');
                }

                if ($operation === PostContentOperation::Custom && blank($this->input('instructions'))) {
                    $validator->errors()->add('instructions', 'Add custom instructions for this operation.');
                }

                if ($operation === PostContentOperation::Ideas
                    && blank($this->input('topic'))
                    && $this->user()?->brandProfile === null) {
                    $validator->errors()->add('topic', 'Add a topic or create a brand profile for content ideas.');
                }
            },
        ];
    }

    /**
     * Get the validated request as the content generator's input shape.
     *
     * @return array{
     *     operation: string,
     *     topic: string|null,
     *     content: string|null,
     *     platform: string,
     *     format: string|null,
     *     tone: string,
     *     language: string,
     *     audience: string|null,
     *     instructions: string|null,
     *     variants: int
     * }
     */
    public function generationInput(): array
    {
        $this->validated();

        return [
            'operation' => $this->string('operation')->toString(),
            'topic' => $this->optionalString('topic'),
            'content' => $this->optionalString('content'),
            'platform' => $this->string('platform')->toString(),
            'format' => $this->optionalString('format'),
            'tone' => $this->string('tone')->toString(),
            'language' => $this->string('language')->toString(),
            'audience' => $this->optionalString('audience'),
            'instructions' => $this->optionalString('instructions'),
            'variants' => $this->integer('variants', 1),
        ];
    }

    private function optionalString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->toString() : null;
    }
}
