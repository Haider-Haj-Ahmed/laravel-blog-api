<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;

class UserSettingsService
{
    public const DEFAULTS = [
        'theme' => 'system',
        'language' => 'en',
        'notifications' => [
            'channels' => [
                'in_app' => true,
                'push' => false,
                'email' => false,
            ],
            'events' => [
                'likes' => true,
                'comments' => true,
                'follows' => true,
                'mentions' => true,
                'highlights' => true,
                'verification' => true,
                'product_updates' => false,
            ],
        ],
        'privacy' => [
            'show_email' => false,
            'profile_discoverable' => true,
            'allow_follows' => true,
            'policy_accepted' => false,
            'policy_version' => null,
        ],
    ];

    public function defaults(): array
    {
        return self::DEFAULTS;
    }

    public function mergeWithDefaults(?array $settings): array
    {
        $normalized = $this->normalizeLegacy($settings ?? []);

        return array_replace_recursive($this->defaults(), $normalized);
    }

    public function applyPatch(?array $current, array $incoming): array
    {
        $normalizedCurrent = $this->normalizeLegacy($current ?? []);
        $normalizedIncoming = $this->normalizeLegacy($incoming);

        return array_replace_recursive($this->defaults(), $normalizedCurrent, $normalizedIncoming);
    }

    public function shouldNotify(User $user, string $eventKey): bool
    {
        $settings = $this->mergeWithDefaults($user->profile?->settings ?? []);
        $channelEnabled = (bool) data_get($settings, 'notifications.channels.in_app', true);
        $eventEnabled = (bool) data_get($settings, 'notifications.events.'.$eventKey, true);

        return $channelEnabled && $eventEnabled;
    }

    public function isProfileDiscoverable(?Profile $profile, ?User $viewer = null): bool
    {
        if (! $profile) {
            return true;
        }

        if ($viewer && $profile->user_id === $viewer->id) {
            return true;
        }

        $settings = $this->mergeWithDefaults($profile->settings ?? []);

        return (bool) data_get($settings, 'privacy.profile_discoverable', true);
    }

    public function allowFollows(?Profile $profile): bool
    {
        if (! $profile) {
            return true;
        }

        $settings = $this->mergeWithDefaults($profile->settings ?? []);

        return (bool) data_get($settings, 'privacy.allow_follows', true);
    }

    public function showEmail(?Profile $profile, ?User $viewer = null): bool
    {
        if (! $profile) {
            return false;
        }

        if ($viewer && $profile->user_id === $viewer->id) {
            return true;
        }

        $settings = $this->mergeWithDefaults($profile->settings ?? []);

        return (bool) data_get($settings, 'privacy.show_email', false);
    }

    private function normalizeLegacy(array $settings): array
    {
        if (array_key_exists('notify_likes', $settings)) {
            $settings['notifications']['events']['likes'] = (bool) $settings['notify_likes'];
            unset($settings['notify_likes']);
        }

        if (array_key_exists('notify_comments', $settings)) {
            $settings['notifications']['events']['comments'] = (bool) $settings['notify_comments'];
            unset($settings['notify_comments']);
        }

        if (array_key_exists('privacy_show_email', $settings)) {
            $settings['privacy']['show_email'] = (bool) $settings['privacy_show_email'];
            unset($settings['privacy_show_email']);
        }

        return $settings;
    }
}
