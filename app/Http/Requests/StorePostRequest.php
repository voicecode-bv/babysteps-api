<?php

namespace App\Http\Requests;

use App\Rules\AccessibleCircle;
use App\Rules\MaxVideoDuration;
use App\Rules\OwnedTag;
use App\Rules\TaggablePerson;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class StorePostRequest extends FormRequest
{
    private const MIME_TYPES = 'jpg,jpeg,png,gif,heic,heif,mp4,mov,m4v';

    private const MAX_KB = 256000;

    public const MAX_MEDIA_ITEMS = 10;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * `media_metadata` is sent as a JSON string by the mobile BFF (multipart
     * does not serialize nested arrays cleanly). Decode it up-front so the
     * standard validator can walk `media_metadata.*.taken_at` etc.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('media_metadata') && is_string($this->input('media_metadata'))) {
            $decoded = json_decode((string) $this->input('media_metadata'), true);

            if (is_array($decoded)) {
                $this->merge(['media_metadata' => $decoded]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->isMultiMedia() ? $this->arrayRules() : $this->singleRules();
    }

    /**
     * Did the client upload `media` (single file) or `media[]` (array)?
     * `$request->file('media')` returns an array iff the field name had
     * array notation.
     */
    public function isMultiMedia(): bool
    {
        return is_array($this->file('media'));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function singleRules(): array
    {
        return [
            'media' => ['required', 'file', 'mimes:'.self::MIME_TYPES, 'max:'.self::MAX_KB, new MaxVideoDuration(180)],
            'caption' => ['nullable', 'string', 'max:2200'],
            'location' => ['nullable', 'string', 'max:255'],
            'taken_at' => ['nullable', 'date', 'before_or_equal:now', 'after:1990-01-01'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'circle_ids' => ['required', 'array', 'min:1', 'max:50'],
            'circle_ids.*' => ['uuid', new AccessibleCircle($this->user())],
            'tag_ids' => ['sometimes', 'array', 'max:50'],
            'tag_ids.*' => ['uuid', new OwnedTag($this->user())],
            'person_ids' => ['sometimes', 'array', 'max:50'],
            'person_ids.*' => ['uuid', new TaggablePerson($this->user(), $this->effectiveCircleIds())],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function arrayRules(): array
    {
        return [
            'media' => ['required', 'array', 'min:1', 'max:'.self::MAX_MEDIA_ITEMS],
            'media.*' => ['file', 'mimes:'.self::MIME_TYPES, 'max:'.self::MAX_KB, new MaxVideoDuration(180)],
            'media_metadata' => ['nullable', 'array', 'max:'.self::MAX_MEDIA_ITEMS],
            'media_metadata.*' => ['array'],
            'media_metadata.*.taken_at' => ['nullable', 'date', 'before_or_equal:now', 'after:1990-01-01'],
            'media_metadata.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'media_metadata.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'caption' => ['nullable', 'string', 'max:2200'],
            'location' => ['nullable', 'string', 'max:255'],
            'circle_ids' => ['required', 'array', 'min:1', 'max:50'],
            'circle_ids.*' => ['uuid', new AccessibleCircle($this->user())],
            'tag_ids' => ['sometimes', 'array', 'max:50'],
            'tag_ids.*' => ['uuid', new OwnedTag($this->user())],
            'person_ids' => ['sometimes', 'array', 'max:50'],
            'person_ids.*' => ['uuid', new TaggablePerson($this->user(), $this->effectiveCircleIds())],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function effectiveCircleIds(): array
    {
        return array_values(array_filter(array_map(
            fn ($id) => is_string($id) ? $id : null,
            (array) $this->input('circle_ids', [])
        )));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            if ($this->isMultiMedia()) {
                $this->validateNoMixedVideoPhoto($v);
                $this->validatePerItemCoordinates($v);

                return;
            }

            $this->validateTopLevelCoordinates($v);
        });
    }

    private function validateTopLevelCoordinates(Validator $v): void
    {
        if ($this->filled('latitude') !== $this->filled('longitude')) {
            $v->errors()->add('longitude', 'Latitude and longitude must be provided together.');
        }
    }

    private function validatePerItemCoordinates(Validator $v): void
    {
        foreach ((array) $this->input('media_metadata', []) as $index => $meta) {
            if (! is_array($meta)) {
                continue;
            }

            $hasLat = isset($meta['latitude']) && $meta['latitude'] !== null;
            $hasLng = isset($meta['longitude']) && $meta['longitude'] !== null;

            if ($hasLat !== $hasLng) {
                $v->errors()->add(
                    "media_metadata.{$index}.longitude",
                    'Latitude and longitude must be provided together.'
                );
            }
        }
    }

    private function validateNoMixedVideoPhoto(Validator $v): void
    {
        $files = (array) $this->file('media');

        if (count($files) <= 1) {
            return;
        }

        foreach ($files as $index => $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            if (str_starts_with((string) $file->getMimeType(), 'video/')) {
                $v->errors()->add(
                    "media.{$index}",
                    'A video must be uploaded on its own, without other photos.'
                );

                return;
            }
        }
    }
}
