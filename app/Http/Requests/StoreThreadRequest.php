<?php

namespace App\Http\Requests;

use App\Models\Thread;
use App\Rules\Hashtag;
use Illuminate\Foundation\Http\FormRequest;

class StoreThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Settle the topic on one spelling before it is validated or stored, so the
     * tag filter does not end up with #Advice sitting next to #advice.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('topic')) {
            $this->merge([
                'topic' => strtolower(ltrim(trim((string) $this->input('topic')), '#')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.Thread::MAX_TITLE_LENGTH],
            'body' => ['required', 'string', 'max:'.Thread::MAX_BODY_LENGTH],
            'topic' => ['required', 'string', new Hashtag(Thread::TOPICS)],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Give your thread a title so people know what it is about.',
            'body.required' => 'Write something for people to reply to.',
            'topic.required' => 'Please pick or write a topic for your thread.',
        ];
    }
}
