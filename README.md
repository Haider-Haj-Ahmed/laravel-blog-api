# TechTalk API

Laravel REST API for TechTalk mobile clients and admin tooling.

## Overview

- Framework: Laravel 12, PHP 8.2+
- Auth: Sanctum bearer tokens
- Admin: Filament panel at `/admin`
- API prefix: `/api`

## Auth and onboarding

- `POST /api/register`: creates user + profile and sends OTP
- `POST /api/otp/verify`: verifies OTP and returns `access_token`
- `POST /api/otp/resend`: resends OTP (`user_id`, optional `channel`)
- `POST /api/login`: requires account already verified
- `POST /api/logout`: revokes current user tokens (auth required)

## Data model highlights

- Posts and blogs support likes and comments.
- Saves are polymorphic bookmarks for `post` and `blog`.
- Views are polymorphic records for `post`, `blog`, and `profile`.
- Tags are reusable and can be attached to posts/blogs/profile.

## API route inventory (from `routes/api.php`)

### Public routes

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/register` | Register user and trigger OTP flow |
| POST | `/api/login` | Login (verified account required) |
| POST | `/api/otp/verify` | Verify OTP |
| POST | `/api/otp/resend` | Resend OTP |
| GET | `/api/posts` | Published posts list |
| GET | `/api/posts/{post}` | Post details |
| GET | `/api/posts/{post}/comments` | Post comments |
| GET | `/api/blogs/{blog}/comments` | Blog comments |
| GET | `/api/blogs` | Published blogs list |
| GET | `/api/blogs/{blog}` | Blog details |
| GET | `/api/users/{username}` | User summary |
| GET | `/api/users/{username}/profile` | Public profile |
| GET | `/api/users/{username}/posts` | User published posts |
| GET | `/api/users/{username}/blogs` | User published blogs |
| GET | `/api/roadmaps` | Road maps list |
| GET | `/api/roadmaps/{id}` | Road map details (with nodes) |
| GET | `/api/tags` | Tags list |
| GET | `/api/profiles` | Profiles list |
| POST | `/api/search` | Search posts/blogs/users |
| POST | `/api/analyze-code` | AI code safety/classification helper |
| POST | `/api/compile` | Compiler proxy |
| POST | `/api/generate-uml` | UML image generation |

### Protected routes (`auth:sanctum`)

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/logout` | Logout current user |
| POST | `/api/posts` | Create post |
| PUT | `/api/posts/{post}` | Deprecated legacy update endpoint (returns 404) |
| PUT | `/api/posts/{post}/content` | Update own post content |
| POST | `/api/posts/{post}/photos` | Add one post photo |
| PUT | `/api/posts/{post}/photos/{photo}` | Replace one post photo |
| DELETE | `/api/posts/{post}/photos/{photo}` | Delete one post photo |
| DELETE | `/api/posts/{post}` | Delete own post |
| GET | `/api/posts/recommended` | Personalized feed |
| GET | `/api/posts/drafts` | Current user draft posts |
| GET | `/api/posts/viewers/{id}` | Post viewers |
| POST | `/api/posts/{post}/toggle-like` | Toggle post like |
| POST | `/api/blogs/{blog}/toggle-like` | Toggle blog like |
| GET | `/api/blogs/drafts` | Current user draft blogs |
| GET | `/api/blogs/viewers/{id}` | Blog viewers |
| POST | `/api/comments` | Create comment for post/blog |
| GET | `/api/comments/{comment}` | Comment details |
| POST | `/api/comments/{comment}` | Update own comment |
| POST | `/api/comments/{comment}/like` | Like/unlike-style toggle for comment likes |
| POST | `/api/comments/{comment}/dislike` | Toggle comment dislike |
| GET | `/api/comments/{comment}/children` | Paginated child comments |
| POST | `/api/comments/{comment}/highlight` | Highlight comment (content owner only) |
| GET | `/api/notifications` | Notifications list |
| GET | `/api/notifications/unread-count` | Unread notifications count |
| PATCH | `/api/notifications/{notification}/read` | Mark one notification as read |
| PATCH | `/api/notifications/read-all` | Mark all as read |
| GET | `/api/activity` | Activity history |
| GET | `/api/saved` | Saved items list (`type=post|blog|all`) |
| POST | `/api/saves` | Save content (`type`, `id`) |
| DELETE | `/api/saves` | Remove saved content (`type`, `id`) |
| POST | `/api/views` | Record view (`type=post|blog|profile`, `id`) |
| GET | `/api/show-me` | Get current user's profile |
| PUT | `/api/profile` | Update own profile |
| GET | `/api/profiles/{profile}` | Profile details by profile id |
| GET | `/api/profiles/viewers/{id}` | Profile viewers |
| POST | `/api/users/{username}/follow` | Follow user |
| DELETE | `/api/users/{username}/follow` | Unfollow user |
| POST | `/api/updatepost/tags/{post}` | Sync tags on own post |
| POST | `/api/updateprofile/tags/{profile}` | Sync tags on own profile |
| POST | `/api/survy` | Initial profile tags survey |
| POST | `/api/suggestions` | Mention suggestions (`q`) |
| apiResource | `/api/blogs` | Used for write operations; index/show also have dedicated public GET routes |

## Response format notes

- Most controllers use `App\Traits\ApiResponseTrait` with shape:
  - success: `{ "status": "success", "message": "...", "data": ... }`
  - error: `{ "status": "error", "message": "...", "errors": ... }`
- Paginated endpoints include `pagination` metadata.
- Non-JSON endpoints (for example image/binary responses) may bypass this envelope intentionally.

## Content resource notes

- `PostResource` computes `type` as: `text`, `text_photo`, `text_code`, `text_code_photo`.
- `CommentResource` computes `type` as: `text` or `text_code`.
- `is_liked_by_user` currently resolves to `false` when unauthenticated for post/blog resources.

## Local setup

```cmd
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Optional integrations in `.env`: Pusher, Twilio, mail providers, and AI provider keys used by utility endpoints.

## Related docs

- `docs/API_RESPONSE_TRAIT.md`
- `docs/API_CONTRACT.md`
- `docs/api-contract.openapi.yaml`
- `LIKES_IMPLEMENTATION_CHECKLIST.md`
- `POSTMAN_LIKES_TESTING.md`
