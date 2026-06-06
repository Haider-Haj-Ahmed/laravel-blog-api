<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingsRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_SETTINGS_KEYS = [
        'theme',
        'language',
        'notifications',
        'privacy',
        'notify_likes',
        'notify_comments',
        'privacy_show_email',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_NOTIFICATION_KEYS = [
        'channels',
        'events',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_NOTIFICATION_CHANNEL_KEYS = [
        'in_app',
        'push',
        'email',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_NOTIFICATION_EVENT_KEYS = [
        'likes',
        'comments',
        'follows',
        'mentions',
        'highlights',
        'verification',
        'product_updates',
    ];

    /**
     * @var list<string>
     */
    private const ALLOWED_PRIVACY_KEYS = [
        'show_email',
        'profile_discoverable',
        'allow_follows',
        'policy_accepted',
        'policy_version',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach (array_keys($this->input()) as $key) {
                if (!in_array($key, self::ALLOWED_SETTINGS_KEYS, true)) {
                    $validator->errors()->add($key, 'Unknown setting.');
                }
            }

            $notifications = $this->input('notifications');
            if (is_array($notifications)) {
                foreach (array_keys($notifications) as $key) {
                    if (!in_array($key, self::ALLOWED_NOTIFICATION_KEYS, true)) {
                        $validator->errors()->add('notifications.' . $key, 'Unknown notification setting.');
                    }
                }

                $channels = $notifications['channels'] ?? null;
                if (is_array($channels)) {
                    foreach (array_keys($channels) as $key) {
                        if (!in_array($key, self::ALLOWED_NOTIFICATION_CHANNEL_KEYS, true)) {
                            $validator->errors()->add('notifications.channels.' . $key, 'Unknown notification channel.');
                        }
                    }
                }

                $events = $notifications['events'] ?? null;
                if (is_array($events)) {
                    foreach (array_keys($events) as $key) {
                        if (!in_array($key, self::ALLOWED_NOTIFICATION_EVENT_KEYS, true)) {
                            $validator->errors()->add('notifications.events.' . $key, 'Unknown notification event.');
                        }
                    }
                }
            }

            $privacy = $this->input('privacy');
            if (is_array($privacy)) {
                foreach (array_keys($privacy) as $key) {
                    if (!in_array($key, self::ALLOWED_PRIVACY_KEYS, true)) {
                        $validator->errors()->add('privacy.' . $key, 'Unknown privacy setting.');
                    }
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
            'language' => 'sometimes|string|in:en,fr,ar',
            'notifications' => 'sometimes|array',
            'notifications.channels' => 'sometimes|array',
            'notifications.channels.in_app' => 'sometimes|boolean',
            'notifications.channels.push' => 'sometimes|boolean',
            'notifications.channels.email' => 'sometimes|boolean',
            'notifications.events' => 'sometimes|array',
            'notifications.events.likes' => 'sometimes|boolean',
            'notifications.events.comments' => 'sometimes|boolean',
            'notifications.events.follows' => 'sometimes|boolean',
            'notifications.events.mentions' => 'sometimes|boolean',
            'notifications.events.highlights' => 'sometimes|boolean',
            'notifications.events.verification' => 'sometimes|boolean',
            'notifications.events.product_updates' => 'sometimes|boolean',
            'privacy' => 'sometimes|array',
            'privacy.show_email' => 'sometimes|boolean',
            'privacy.profile_discoverable' => 'sometimes|boolean',
            'privacy.allow_follows' => 'sometimes|boolean',
            'privacy.policy_accepted' => 'sometimes|boolean',
            'privacy.policy_version' => 'sometimes|nullable|string|max:40',
            'notify_likes' => 'sometimes|boolean',
            'notify_comments' => 'sometimes|boolean',
            'privacy_show_email' => 'sometimes|boolean',
        ];
    }
}
