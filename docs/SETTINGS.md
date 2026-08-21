# Settings API — Frontend Guide

How to load, display, and update authenticated user settings without guesswork.

Machine-readable twin: [`settings.openapi.yaml`](./settings.openapi.yaml)  
Backend sources: `SettingsController`, `UpdateSettingsRequest`, `UserSettingsService`

---

## Quick start

| | |
|---|---|
| **Base path** | `/api` |
| **Auth** | Sanctum: `Authorization: Bearer <token>` |
| **GET** | `GET /api/settings` → full settings object |
| **PATCH** | `PATCH /api/settings` → deep-merge patch, returns full object |
| **Content-Type** | `application/json` |
| **Do not** | send `settings` on `PUT /api/profile` (prohibited) |

### Rate limits

| Endpoint | Limit |
|---|---|
| `GET /settings` | **30 / minute** (user id, else IP) |
| `PATCH /settings` | **10 / minute** (user id, else IP) |

On exceed → **429**.

---

## Response envelope

Success:

```json
{
  "status": "success",
  "message": "Settings retrieved successfully",
  "data": {
    "settings": { }
  }
}
```

Error (validation always uses this `message`; details are in `errors`):

```json
{
  "status": "error",
  "message": "Validation failed",
  "errors": {
    "theme": ["The selected theme is invalid."]
  }
}
```

Other errors use `status: "error"` with messages like `Unauthorized …`, `Forbidden …`, or `Too many requests …`.

Always read settings from **`data.settings`**. After every successful GET or PATCH, replace your local store with that object — it is already complete and merged.

---

## Canonical settings object

This is exactly what GET returns (and what PATCH returns after merge). Use it as your TypeScript / Zod model.

```json
{
  "theme": "system",
  "language": "en",
  "notifications": {
    "channels": {
      "in_app": true,
      "push": false,
      "email": false
    },
    "events": {
      "likes": true,
      "comments": true,
      "follows": true,
      "mentions": true,
      "highlights": true,
      "verification": true,
      "product_updates": false
    }
  },
  "privacy": {
    "show_email": false,
    "profile_discoverable": true,
    "allow_follows": true,
    "policy_accepted": false,
    "policy_version": null
  }
}
```

### Field reference

#### Top level

| Key | Type | Allowed values | Default | UI hint |
|---|---|---|---|---|
| `theme` | string | `light`, `dark`, `system` | `system` | Theme picker |
| `language` | string | `en`, `fr`, `ar` | `en` | Locale picker (**not** free text) |

#### `notifications.channels`

| Key | Type | Default | Meaning |
|---|---|---|---|
| `in_app` | boolean | `true` | Master switch for **in-app** notification creation. Backend delivery checks this. |
| `push` | boolean | `false` | Stored push preference (UI should keep it; push pipeline not enforced in `shouldNotify` yet). |
| `email` | boolean | `false` | Stored email preference (same: persist in UI; not enforced in `shouldNotify` yet). |

#### `notifications.events`

| Key | Type | Default | When it matters |
|---|---|---|---|
| `likes` | boolean | `true` | Content like events |
| `comments` | boolean | `true` | Comment events |
| `follows` | boolean | `true` | New follower |
| `mentions` | boolean | `true` | @mentions |
| `highlights` | boolean | `true` | Highlight events |
| `verification` | boolean | `true` | Verification-related notices |
| `product_updates` | boolean | `false` | Product / marketing style updates |

**Delivery rule today:** a notification is created only if  
`notifications.channels.in_app === true` **and** the matching `notifications.events.<key> === true`.

So if the user turns off **In-app**, all event toggles are effectively muted for server delivery until in-app is on again. Still show both channel and event controls; do not invent client-only side effects beyond that.

#### `privacy`

| Key | Type | Default | Effect on the product |
|---|---|---|---|
| `show_email` | boolean | `false` | Other users do **not** get `email` on profile payloads. Owner always sees own email. |
| `profile_discoverable` | boolean | `true` | When `false`, profile is hidden from discovery/search for others. Owner can still see self. |
| `allow_follows` | boolean | `true` | When `false`, `POST /users/{username}/follow` → **403** (`This user does not accept follows.`). |
| `policy_accepted` | boolean | `false` | ToS / policy acceptance flag for your onboarding gate. |
| `policy_version` | string \| null | `null` | Version id of accepted policy (`max 40` chars). Send `null` to clear. |

---

## `GET /api/settings`

### Behavior

1. Requires auth.
2. Creates an empty profile if missing.
3. Merges stored JSON with server defaults.
4. If the merge changed anything (missing keys / legacy keys), **saves** the canonical object.
5. Returns `{ data: { settings } }`.

### Example

```http
GET /api/settings HTTP/1.1
Authorization: Bearer {token}
Accept: application/json
```

```json
{
  "status": "success",
  "message": "Settings retrieved successfully",
  "data": {
    "settings": {
      "theme": "system",
      "language": "en",
      "notifications": { "...": "..." },
      "privacy": { "...": "..." }
    }
  }
}
```

### Status codes

| Code | When |
|---|---|
| 200 | OK |
| 401 | Not authenticated |
| 403 | Not the profile owner (should not happen for normal clients) |
| 429 | Rate limited |

**Frontend tip:** call GET once after login (or when opening Settings). Seed your form from `data.settings`. Do not invent defaults on the client that disagree with the server — the server always fills gaps.

---

## `PATCH /api/settings`

### Behavior

Deep merge (recursive):

```
final = defaults ⊕ current_stored ⊕ incoming_patch
```

- Only send fields you want to change.
- Omitted keys and omitted nested siblings are **kept**.
- Response always contains the **full** merged settings object — use it to refresh local state.
- Creates a profile if missing (same as GET).

### Examples

Theme only:

```http
PATCH /api/settings HTTP/1.1
Authorization: Bearer {token}
Content-Type: application/json

{ "theme": "dark" }
```

One nested notification flag (other events untouched):

```json
{
  "notifications": {
    "events": {
      "likes": false
    }
  }
}
```

Privacy + language:

```json
{
  "language": "ar",
  "privacy": {
    "show_email": true,
    "allow_follows": false
  }
}
```

Policy acceptance:

```json
{
  "privacy": {
    "policy_accepted": true,
    "policy_version": "2026-03-01"
  }
}
```

### Validation (strict)

| Field | Rule |
|---|---|
| `theme` | `light` \| `dark` \| `system` |
| `language` | `en` \| `fr` \| `ar` |
| All channel/event/privacy flags | JSON **boolean** (`true` / `false`) |
| `privacy.policy_version` | string `max:40`, or `null` |
| Unknown keys (any level) | **422** (`Unknown setting.` / `Unknown notification …` / `Unknown privacy setting.`) |

Allowed top-level keys on PATCH:

- Canonical: `theme`, `language`, `notifications`, `privacy`
- Legacy (deprecated, still accepted): `notify_likes`, `notify_comments`, `privacy_show_email`

**Not accepted** as top-level patch keys (will 422): `email_notifications`, `push_notifications`, or any invented key. Use:

```json
{ "notifications": { "channels": { "email": true, "push": false } } }
```

### Status codes

| Code | When |
|---|---|
| 200 | Updated; `data.settings` is the new full object |
| 401 | Not authenticated |
| 403 | Not allowed to update this profile |
| 422 | Invalid value or unknown key |
| 429 | Rate limited |

---

## Recommended frontend flow

```text
Login / open Settings
        │
        ▼
 GET /api/settings  ──►  store.data.settings
        │
        ▼
 User toggles one control
        │
        ▼
 PATCH only the changed path(s)
        │
        ▼
 Replace store with response.data.settings
        │
        ▼
 Apply theme / language locally from store
```

### Suggested TypeScript shape

```ts
export type Theme = 'light' | 'dark' | 'system';
export type Language = 'en' | 'fr' | 'ar';

export interface UserSettings {
  theme: Theme;
  language: Language;
  notifications: {
    channels: {
      in_app: boolean;
      push: boolean;
      email: boolean;
    };
    events: {
      likes: boolean;
      comments: boolean;
      follows: boolean;
      mentions: boolean;
      highlights: boolean;
      verification: boolean;
      product_updates: boolean;
    };
  };
  privacy: {
    show_email: boolean;
    profile_discoverable: boolean;
    allow_follows: boolean;
    policy_accepted: boolean;
    policy_version: string | null;
  };
}
```

### Patch helpers (examples)

```ts
// Prefer nested paths that match the API
patchSettings({ theme: 'dark' });

patchSettings({
  notifications: { events: { mentions: false } },
});

patchSettings({
  privacy: { profile_discoverable: false },
});
```

Optimistic UI is fine if you roll back on **422** / **429** and re-GET when unsure.

---

## Side effects outside the Settings screen

These settings change other API behavior. Wire UI accordingly.

| Setting | What the user will notice elsewhere |
|---|---|
| `privacy.show_email = false` | Other viewers’ profile responses omit `email`. |
| `privacy.profile_discoverable = false` | Profile hidden from search / discovery for others. |
| `privacy.allow_follows = false` | Followers get **403** when trying to follow. Hide or disable “Follow” when you know the target disallows follows (or handle 403). |
| `notifications.channels.in_app = false` | Server stops creating in-app notifications for that user. |
| `notifications.events.* = false` | That event type is skipped (when in-app is on). |
| `theme` / `language` | Pure client preference unless you also drive i18n/theme from these values. |

Settings are **not** returned inside `ProfileResource`. Fetch them from `/settings` for the logged-in user only.

---

## Legacy keys (compatibility only)

Prefer nested keys. These flat keys still work on PATCH and are normalized server-side:

| Legacy key | Becomes |
|---|---|
| `notify_likes` | `notifications.events.likes` |
| `notify_comments` | `notifications.events.comments` |
| `privacy_show_email` | `privacy.show_email` |

Do not send legacy keys in new frontend code.

---

## Common mistakes (avoid these)

1. **Putting settings on profile update** — `PUT /api/profile` rejects `settings`. Use `PATCH /api/settings`.
2. **Sending free-form `language`** — only `en`, `fr`, `ar`.
3. **Sending unknown nested keys** — e.g. `notifications.events.dms` → 422.
4. **Replacing the whole tree in local state from a tiny PATCH body** — always use the **response** `data.settings`.
5. **Assuming `push` / `email` channels already mute server delivery** — only `in_app` + event flags are enforced in `shouldNotify` today. Still store and display push/email for the product UI.
6. **Forgetting auth** — both endpoints require Sanctum.
7. **Spamming PATCH on every keystroke** — respect the **10/min** update limiter; debounce toggles if needed.

---

## Curl cheatsheet

```bash
# Read
curl -s -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  https://example.com/api/settings

# Patch theme
curl -s -X PATCH -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"theme":"dark"}' \
  https://example.com/api/settings

# Patch nested event
curl -s -X PATCH -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"notifications":{"events":{"likes":false}}}' \
  https://example.com/api/settings
```

---

## Defaults (copy-paste)

Same object as `UserSettingsService::DEFAULTS`:

```json
{
  "theme": "system",
  "language": "en",
  "notifications": {
    "channels": {
      "in_app": true,
      "push": false,
      "email": false
    },
    "events": {
      "likes": true,
      "comments": true,
      "follows": true,
      "mentions": true,
      "highlights": true,
      "verification": true,
      "product_updates": false
    }
  },
  "privacy": {
    "show_email": false,
    "profile_discoverable": true,
    "allow_follows": true,
    "policy_accepted": false,
    "policy_version": null
  }
}
```

Use this only as a fallback before the first successful GET. After GET/PATCH, trust the server.
