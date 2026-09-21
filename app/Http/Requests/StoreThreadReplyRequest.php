<?php

namespace App\Http\Requests;

use App\Models\ThreadReply;
use Illuminate\Foundation\Http\FormRequest;

class StoreThreadReplyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.ThreadReply::MAX_BODY_LENGTH],
            'parent_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Write a reply before posting.',
        ];
    }
}
