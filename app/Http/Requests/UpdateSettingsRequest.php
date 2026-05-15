<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $allowed = ['theme', 'notify_likes', 'notify_comments', 'language', 'privacy_show_email'];
            foreach (array_keys($this->input()) as $key) {
                if (! in_array($key, $allowed, true)) {
                    $validator->errors()->add($key, 'Unknown setting.');
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'theme' => 'sometimes|string|in:light,dark,system',
            'notify_likes' => 'sometimes|boolean',
            'notify_comments' => 'sometimes|boolean',
            'language' => 'sometimes|string|max:12',
            'privacy_show_email' => 'sometimes|boolean',
        ];
    }
}
