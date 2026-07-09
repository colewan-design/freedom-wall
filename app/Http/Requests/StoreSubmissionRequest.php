<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'images' => ['nullable', 'array', 'max:4'],
            'images.*' => ['file', 'mimes:jpeg,png,webp', 'max:5120'],
            'captchaToken' => ['nullable', 'string'],
        ];
    }
}
