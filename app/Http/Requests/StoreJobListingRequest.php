<?php

namespace App\Http\Requests;

use App\Models\JobListing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A vacancy a reader found somewhere and wants on the board. */
class StoreJobListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'apply_url' => trim((string) $this->input('apply_url')),
            'is_remote' => $this->boolean('is_remote'),
        ]);
    }

    public function rules(): array
    {
        return [
            'apply_url' => ['required', 'string', 'max:2048', 'url:http,https'],
            'title' => ['required', 'string', 'max:'.JobListing::MAX_TITLE_LENGTH],
            'employer' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:160'],
            'is_remote' => ['boolean'],
            'employment_type' => ['nullable', Rule::in(JobListing::EMPLOYMENT_TYPES)],
            'sector' => ['nullable', Rule::in(JobListing::SECTORS)],
            'salary_text' => ['nullable', 'string', 'max:120'],
            'deadline' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => ['nullable', 'string', 'max:'.JobListing::MAX_DESCRIPTION_LENGTH],
            'captchaToken' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'apply_url.required' => 'Paste the link to the original job post.',
            'apply_url.url' => 'That does not look like a web address. Paste the full link, starting with https://.',
            'title.required' => 'What is the position called?',
            'deadline.after_or_equal' => 'That deadline has already passed.',
        ];
    }

    /** @return array<string, mixed> */
    public function listingData(): array
    {
        $data = $this->safe()->except('captchaToken');

        foreach (['employer', 'location', 'salary_text', 'description'] as $field) {
            $data[$field] = isset($data[$field]) ? (trim((string) $data[$field]) ?: null) : null;
        }

        $data['title'] = trim($data['title']);

        return $data;
    }
}
