<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAttachments;
use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubmissionRequest extends FormRequest
{
    use ValidatesAttachments;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'category' => ['required', 'string', Rule::in(Submission::CATEGORIES)],
            'captchaToken' => ['nullable', 'string'],
            ...$this->attachmentRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Please pick a hashtag for your post.',
            'category.in' => 'Please pick a hashtag from the list.',
            ...$this->attachmentMessages(),
        ];
    }
}
