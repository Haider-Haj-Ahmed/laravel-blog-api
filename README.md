# TeckTalk — IT Blog API

## Overview

**TeckTalk** is an IT-focused blog platform. This repository is the **Laravel REST API** that powers the **TeckTalk mobile app** and works alongside a **web admin dashboard** for operations and content management.

- **Mobile app**: consumes JSON under `/api/*` (Bearer token via Laravel Sanctum).
- **Dashboard**: [Filament](https://filamentphp.com/) v4 admin panel at **`/admin`** (session auth, `web` guard) — currently includes **Users** and **Road maps** resources.

---

## Tech stack

| Layer | Technology |
|--------|------------|
| Framework | Laravel **12** (PHP **8.2+**) |
| API auth | Laravel **Sanctum** |
| Admin UI | **Filament** 4.x |
| DB | MySQL (typical; configure in `.env`) |
| Realtime | Pusher (notifications / broadcasting where configured) |

---

## Features (as implemented)

- **Auth**: register, login, logout; **OTP** verify/resend for onboarding flows.
- **Feed posts**: CRUD (create/update/delete require auth); public index/show; optional **code** + **photo**; response includes a computed **`type`** for UI widgets (`text`, `text_photo`, `text_code`, `text_code_photo`).
- **Post likes**: toggle like per user (`likes` table).
- **Comments** on posts: create/update; nested replies via `parent_id`; like/dislike; optional **code** block; **`type`** in JSON (`text` or `text_code`); **@mentions** in comment body with notifications.
- **Blogs**: long-form articles (separate from feed posts); publish flag; create restricted by profile **badge** (expert) via policy.
- **Profiles**: per-user profile (bio, avatar, cover, links, ranking/badge); public profile by username; authenticated profile update (multipart uploads).
- **Notifications**: list, mark read, mark all read (database notifications).
- **Extras** (public or utility): code compile proxy, UML generation, code analysis (testing), **road maps** API, Filament-managed road maps in admin.

---

## Post & comment `type` (for the mobile UI)

Responses use a **`type`** string so the app can pick the right component.

**Posts** (`PostResource`): derived from stored `code` and `photo` filename.

| `type` | Meaning |
|--------|---------|
| `text` | Body only |
| `text_photo` | Body + image (`photo_url`) |
| `text_code` | Body + `code` |
| `text_code_photo` | Body + `code` + image |

**Comments** (`CommentResource`):

| `type` | Meaning |
|--------|---------|
| `text` | Body only (may still include `@mentions` in `body`) |
| `text_code` | Body + `code` |

Creating a post with a photo uses **multipart** form data; send `photo` as a file. See `StorePostRequest` and `PostController`.

---

## API base URL

All routes in `routes/api.php` are served with the **`/api` prefix** (Laravel default).

Example: `https://your-domain.com/api/posts`

---

## Authentication

1. `POST /api/register` — creates user (+ profile) and OTP flow as implemented in `AuthController`.
2. `POST /api/login` — returns Sanctum **`access_token`** (Bearer).
3. Send header: `Authorization: Bearer {token}` on protected routes.
4. `POST /api/logout` — revokes tokens (requires auth).

OTP:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/otp/verify` | Verify OTP |
| POST | `/api/otp/resend` | Resend OTP |

---

## API endpoints (summary)

### Auth

| Method | Endpoint | Auth |
|--------|----------|------|
| POST | `/api/register` | No |
| POST | `/api/login` | No |
| POST | `/api/logout` | Yes |

### Posts

| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/posts` | No |
| GET | `/api/posts/{post}` | No |
| POST | `/api/posts` | Yes |
| PUT | `/api/posts/{post}` | Yes |
| DELETE | `/api/posts/{post}` | Yes |
| POST | `/api/posts/{post}/toggle-like` | Yes |

### Comments

| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/posts/{post}/comments` | No |
| POST | `/api/comments` | Yes — body includes `post_id` |
| GET | `/api/comments/{comment}` | Yes |
| POST | `/api/comments/{comment}` | Yes — update comment |
| POST | `/api/comments/{comment}/like` | Yes |
| POST | `/api/comments/{comment}/dislike` | Yes |
| GET | `/api/comments/{comment}/children` | Yes |
| POST | `/api/comments/{comment}/highlight` | Yes — post author highlights comment |

### Blogs

| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/blogs` | No — published list |
| GET | `/api/blogs/{blog}` | No |
| POST, PUT, PATCH, DELETE | `/api/blogs`, `/api/blogs/{blog}` | Yes — `apiResource` |

### Users & profile

| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/users/{username}` | No |
| GET | `/api/users/{username}/profile` | No |
| GET | `/api/users/{username}/posts` | No |
| GET | `/api/users/{username}/blogs` | No |
| PUT | `/api/profile` | Yes — update own profile |

### Notifications

| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/notifications` | Yes |
| PATCH | `/api/notifications/{notification}/read` | Yes |
| PATCH | `/api/notifications/read-all` | Yes |

### Road maps (read API)

| Method | Endpoint | Auth |
|--------|----------|------|
| GET | `/api/roadmaps` | No |
| GET | `/api/roadmaps/{id}` | No |

### Utility / testing routes

| Method | Endpoint | Notes |
|--------|----------|--------|
| POST | `/api/analyze-code` | Code analysis (testing) |
| POST | `/api/compile` | Compiler integration |
| POST | `/api/generate-uml` | UML generation |

---

## Admin dashboard (Filament)

- URL: **`/admin`** (default panel).
- Uses **session** login (`web` guard), separate from API tokens.
- Resources discovered under `app/Filament/Resources` (e.g. **Users**, **Road maps**).

---

## Installation (local)

```bash
git clone <repository-url> blog-restfull-api
cd blog-restfull-api

composer install

cp .env.example .env
php artisan key:generate

# Configure DB in .env, then:
php artisan migrate

# Public disk URLs for avatars, covers, post photos:
php artisan storage:link

php artisan serve
```

Optional: configure **Pusher**, **Twilio**, and mail in `.env` for notifications and OTP channels.

---

## Database (high level)

- **users** — accounts (incl. `username`).
- **profiles** — extended user info, avatar/cover filenames, `ranking_points`, badges (computed in model).
- **posts** — feed posts: `title`, `body`, `code`, `photo`, `is_published`, etc.
- **blogs** — article-style content with `is_published`.
- **comments** — `post_id`, optional `parent_id`, optional `code`, mentions pivot.
- **likes** — user ↔ post likes (unique per user/post).
- **notifications** — Laravel notifications.
- **otps** — OTP codes for verification flows.
- Additional tables for road maps/nodes, comment likes pivot, etc. — see `database/migrations/`.

---

## Documentation & testing

- API responses often use a shared shape via `App\Traits\ApiResponseTrait` (`status`, `message`, `data`, pagination meta where applicable).
- Test with **Postman**, **Insomnia**, or your TeckTalk app against `/api/*`.

---

## License

MIT (same as Laravel skeleton unless changed by the project owners).
