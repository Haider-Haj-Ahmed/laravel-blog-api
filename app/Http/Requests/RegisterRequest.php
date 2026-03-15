<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => 'required|confirmed|min:6',
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|url',
            'website' => 'nullable|url',
            'location' => 'nullable|string|max:255',
            'code' => 'nullable|string',
        ];
    }
}
