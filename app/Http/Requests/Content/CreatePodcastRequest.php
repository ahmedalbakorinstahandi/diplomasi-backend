<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;

class CreatePodcastRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'title'                  => ['required', 'string', 'max:255'],
            'slug'                   => ['nullable', 'string', 'max:255', 'unique:podcasts,slug'],
            'description'            => ['nullable', 'string'],
            'cover_image'            => ['nullable', 'string', 'max:512'],
            'course_id'              => ['nullable', 'integer', 'exists:courses,id'],
            'is_published'           => ['nullable', 'boolean'],
            'is_free'                => ['nullable', 'boolean'],
            'requires_subscription'  => ['nullable', 'boolean'],
            'allow_download'         => ['nullable', 'boolean'],
            'order_index'            => ['nullable', 'integer', 'min:0'],
            'published_at'           => ['nullable', 'date'],

            // Audio source — two mutually exclusive options:
            // 1. audio_url  : external URL (e.g. CDN, Soundcloud)
            // 2. audio_path : relative path returned by general/upload-file
            'audio_url'              => ['nullable', 'string', 'max:2048'],
            'audio_path'             => ['nullable', 'string', 'max:512'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->hasFile('audio_file') && ! $this->filled('audio_url')) {
                // Fully optional — podcasts can be created as drafts without audio
            }
        });
    }
}
