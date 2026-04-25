<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateBlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'subtitle' => 'sometimes|string',
            'reading_time' => 'sometimes|nullable|string|max:50',
            'tags' => 'sometimes|array',
            'tags.*' => 'exists:tags,id',
            'is_published' => 'sometimes|boolean',
            'cover_image' => 'sometimes|nullable|image|max:2048',
            'remove_cover_image' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('cover_image') && $this->boolean('remove_cover_image')) {
                $validator->errors()->add('cover_image', 'The cover image and remove cover image fields cannot be used together.');
                $validator->errors()->add('remove_cover_image', 'The cover image and remove cover image fields cannot be used together.');
            }
        });
    }
}
