<?php

namespace App\Http\Requests;

use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in(Submission::CATEGORIES)],
            'images' => ['nullable', 'array', 'max:4'],
            'images.*' => ['file', 'mimes:jpeg,png,webp', 'max:5120'],
            'captchaToken' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Please pick a hashtag for your post.',
            'category.in' => 'Please pick a hashtag from the list.',
        ];
    }
}
