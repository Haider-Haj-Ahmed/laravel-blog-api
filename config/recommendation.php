<?php

return [
    'cache' => [
        'key_version' => 'v1',
        'lock_seconds' => 5,

        'interest' => [
            'enabled' => true,
            'ttl_minutes' => 15,
        ],

        'feed' => [
            'enabled' => true,
            'ttl_seconds' => 60,
        ],

        'trending' => [
            'enabled' => true,
            'ttl_minutes' => 3,
        ],
    ],

    'rate_limit' => [
        'per_minute' => 30,
    ],

    'feed' => [
        'default_per_page' => 15,
        'max_per_page' => 30,
        'candidate_pool_per_bucket' => 120,
        'hide_previously_viewed_posts' => true,
    ],

    'interest' => [
        // Dynamic top tags window.
        'min_top_tags' => 3,
        'max_top_tags' => 8,
        'coverage_target' => 0.75,

        // Event weights used to build user tag affinity.
        'weights' => [
            'view' => 1.0,
            'like' => 3.0,
            'comment' => 4.0,
            'save' => 5.0,
            'profile_tag_prior' => 2.5,
        ],

        // Recency multipliers for user interactions.
        'decay' => [
            'days_7' => 1.0,
            'days_30' => 0.7,
            'days_90' => 0.4,
        ],
    ],

    'blend' => [
        // Must sum to 1.0 for ideal quota split.
        'followed_with_interest' => 0.50,
        'non_followed_with_interest' => 0.30,
        'trending' => 0.20,
    ],

    'ranking' => [
        // Intra-bucket ranking weights.
        'follow_boost' => 2.0,
        'quality' => [
            'likes' => 0.6,
            'comments' => 0.8,
            'saves' => 1.0,
            'views' => 0.2,
        ],

        // Freshness factor as 1 / (1 + age_hours / half_life_hours)
        'freshness_half_life_hours' => 24,
    ],

    'trending' => [
        'window_days' => 7,
        'weights' => [
            'likes' => 3.0,
            'comments' => 4.0,
            'saves' => 6.0,
            'views' => 1.0,
        ],
    ],

    'diversity' => [
        'max_posts_per_author_per_page' => 2,
    ],
];
