<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class UpdateBlogSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'order' => 'sometimes|integer|min:1',
            'image' => 'sometimes|nullable|image|max:2048',
            'remove_image' => 'sometimes|boolean',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('image') && $this->boolean('remove_image')) {
                $validator->errors()->add('image', 'The image and remove image fields cannot be used together.');
                $validator->errors()->add('remove_image', 'The image and remove image fields cannot be used together.');
            }
        });
    }
}
