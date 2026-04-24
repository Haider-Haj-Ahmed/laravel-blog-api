<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderBlogSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sections' => 'required|array|min:1',
            'sections.*.id' => 'required|integer|distinct|exists:sections,id',
            'sections.*.order' => 'required|integer|min:1|distinct',
        ];
    }
}
