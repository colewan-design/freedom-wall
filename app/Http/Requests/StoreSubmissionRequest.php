<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesAttachments;
use App\Rules\Hashtag;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    use ValidatesAttachments;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Take the hashtag however someone typed it — with or without the #, in any
     * casing — and settle on one spelling before it is validated or stored, so
     * the wall does not end up with #Rant sitting next to #rant.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('category')) {
            $this->merge([
                'category' => strtolower(ltrim(trim((string) $this->input('category')), '#')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
            'category' => ['required', 'string', new Hashtag],
            'captchaToken' => ['nullable', 'string'],
            ...$this->attachmentRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'category.required' => 'Please pick or write a hashtag for your post.',
            ...$this->attachmentMessages(),
        ];
    }
}
