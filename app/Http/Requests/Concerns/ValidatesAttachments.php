<?php

namespace App\Http\Requests\Concerns;

use App\Rules\MediaAttachment;
use Illuminate\Validation\Validator;

/**
 * Shared by every composer that accepts uploads, so the wall and the student
 * feed always agree on what may be attached and how much of it.
 *
 * The field is still named `images` — it is the JSON column posts and
 * submissions already store their media paths in — but it carries videos too.
 */
trait ValidatesAttachments
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function attachmentRules(): array
    {
        return [
            'images' => ['nullable', 'array', 'max:'.MediaAttachment::MAX_ATTACHMENTS],
            'images.*' => ['file', new MediaAttachment],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function attachmentMessages(): array
    {
        return [
            'images.max' => 'You can attach up to '.MediaAttachment::MAX_ATTACHMENTS.' files to a post.',
        ];
    }

    /**
     * Counting videos needs the whole set, which a per-file rule never sees.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $videos = count(array_filter(
                    (array) $this->file('images', []),
                    MediaAttachment::isVideo(...),
                ));

                if ($videos > MediaAttachment::MAX_VIDEOS) {
                    $validator->errors()->add('images', 'You can attach one video per post.');
                }
            },
        ];
    }
}
