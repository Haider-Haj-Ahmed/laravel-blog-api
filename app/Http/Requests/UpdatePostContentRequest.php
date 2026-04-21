<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
            'code' => 'sometimes|nullable|string',
            'code_language' => 'sometimes|nullable|string|max:50',
            'is_published' => 'sometimes|boolean',
            'tags' => 'sometimes|array',
            'tags.*' => 'exists:tags,id',
        ];
    }
}
