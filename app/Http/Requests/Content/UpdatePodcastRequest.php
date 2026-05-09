<?php

namespace App\Http\Requests\Content;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdatePodcastRequest extends BaseFormRequest
{
    public function rules(): array
    {
        $podcastId = $this->route('id');

        return [
            'title'                  => ['sometimes', 'required', 'string', 'max:255'],
            'slug'                   => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('podcasts', 'slug')->ignore($podcastId)],
            'description'            => ['sometimes', 'nullable', 'string'],
            'cover_image'            => ['sometimes', 'nullable', 'string', 'max:512'],
            'course_id'              => ['sometimes', 'nullable', 'integer', 'exists:courses,id'],
            'is_published'           => ['sometimes', 'nullable', 'boolean'],
            'is_free'                => ['sometimes', 'nullable', 'boolean'],
            'requires_subscription'  => ['sometimes', 'nullable', 'boolean'],
            'allow_download'         => ['sometimes', 'nullable', 'boolean'],
            'order_index'            => ['sometimes', 'nullable', 'integer', 'min:0'],
            'published_at'           => ['sometimes', 'nullable', 'date'],
            'duration_seconds'       => ['sometimes', 'nullable', 'integer', 'min:0'],

            // Audio source — two mutually exclusive options:
            // 1. audio_url  : external URL (e.g. CDN, Soundcloud)
            // 2. audio_path : relative path returned by general/upload-file
            'audio_url'              => ['sometimes', 'nullable', 'string', 'max:2048'],
            'audio_path'             => ['sometimes', 'nullable', 'string', 'max:512'],
        ];
    }
}
