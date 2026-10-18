<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBrandProfileRequest extends FormRequest
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
            'brand_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'industry' => ['nullable', 'string', 'max:120'],
            'target_audience' => ['nullable', 'string', 'max:500'],
            'preferred_tone' => ['nullable', 'string', 'max:100'],
            'preferred_languages' => ['required', 'array', 'min:1', 'max:2'],
            'preferred_languages.*' => ['required', 'string', 'distinct', Rule::in(['English', 'Persian'])],
            'content_guidelines' => ['nullable', 'string', 'max:3000'],
            'avoid_terms' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
