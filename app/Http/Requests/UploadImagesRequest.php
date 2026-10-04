<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class UploadImagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission(config('images.permissions')) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'min:1', 'max:'.config('images.max_files')],
            'images.*' => ['required', 'file', 'image', 'dimensions:min_width=1,min_height=1', 'mimes:'.implode(',', config('images.upload_extensions')), 'extensions:'.implode(',', config('images.upload_extensions')), 'max:'.self::maximumSizeKilobytes()],
            'file_name' => ['nullable', 'string', 'max:180'],
        ];
    }

    public static function maximumSizeKilobytes(): int
    {
        return min(config('images.max_size_kb'), (int) floor(UploadedFile::getMaxFilesize() / 1024));
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('image') && ! $this->hasFile('images')) {
            $this->files->set('images', [$this->file('image')]);
            $this->convertedFiles = null;
        }
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! is_string($this->input('file_name')) || ! $this->filled('file_name')) {
                return;
            }

            if (Str::slug(pathinfo($this->input('file_name'), PATHINFO_FILENAME)) === '') {
                $validator->errors()->add('file_name', 'Choose a file name containing letters or numbers.');
            }

            if (is_array($this->file('images')) && count($this->file('images')) > 1) {
                $validator->errors()->add('file_name', 'A custom name can only be used when uploading one image.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Choose at least one image to upload.',
            'images.max' => 'Choose up to :max images at a time.',
            'images.*.image' => 'Each file must be a valid image.',
            'images.*.dimensions' => 'This image could not be read. Choose a valid image file.',
            'images.*.mimes' => 'Use PNG, JPG, GIF or WebP images.',
            'images.*.extensions' => 'Use a PNG, JPG, GIF or WebP file extension.',
            'images.*.max' => 'Each image must be no larger than :max KB.',
        ];
    }
}
