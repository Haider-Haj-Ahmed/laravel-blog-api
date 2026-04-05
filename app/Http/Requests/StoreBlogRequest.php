<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreBlogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'subtitle' => 'required|string',
            'reading_time'=>'nullable|string|max:50',
            'cover_image' => 'nullable|image|max:2048',
            'tags' => 'array',
            'tags.*' => 'exists:tags,id',
            'sections'=>'required|array|min:1',
            'sections.*.title'=>'required|string|max:255',
            'sections.*.content'=>'required|string',
            'sections.*.order'=>'required|integer|distinct|min:1',
            'sections.*.image'=>'nullable|image|max:2048',
            'is_published' => 'boolean',
        ];
    }
}
