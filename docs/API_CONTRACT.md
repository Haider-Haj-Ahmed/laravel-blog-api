# API Contract (Frontend)

This contract is derived from `routes/api.php` and current controllers/resources.

## 1) Base

- Base path: `/api`
- Auth: `Authorization: Bearer <token>` (Sanctum)
- Content type: `application/json` unless stated otherwise
- Default pagination size: usually `15` (some endpoints use different values)

## 2) Response envelope

All JSON endpoints use this envelope:

- Success

```json
{
  "status": "success",
  "message": "...",
  "data": {}
}
```

- Error

```json
{
  "status": "error",
  "message": "...",
  "errors": {}
}
```

`errors` is present mainly for validation/business validation responses.

### Paginated success envelope

```json
{
  "status": "success",
  "message": "...",
  "data": [],
  "pagination": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 15,
    "total": 0,
    "from": null,
    "to": null,
    "has_more_pages": false
  }
}
```

### Global API errors (normalized)

- `401` -> `{"status":"error","message":"Unauthorized"}`
- `403` -> `{"status":"error","message":"Forbidden"}`
- `404` (model) -> `{"status":"error","message":"Resource not found"}`
- `404` (route) -> `{"status":"error","message":"Route not found"}`
- `405` -> `{"status":"error","message":"Method not allowed"}`
- `422` validation -> `{"status":"error","message":"Validation failed","errors":{...}}`
- `429` -> `{"status":"error","message":"Too many requests"}`
- `500` -> `{"status":"error","message":"Server error"}`

## 3) Primary resource shapes

## `UserSummary`

```json
{
  "id": 1,
  "username": "john",
  "name": "John",
  "avatar_url": "...",
  "bio": "...",
  "badge": "..."
}
```

## `Tag`

```json
{ "id": 1, "name": "php" }
```

## `Section`

```json
{
  "id": 1,
  "title": "Intro",
  "content": "...",
  "order": 1,
  "image_url": "..."
}
```

## `Post`

```json
{
  "id": 1,
  "title": "...",
  "body": "...",
  "code": "...",
  "code_language": "php",
  "photo_url": "...",
  "photos": [{ "id": 1, "url": "...", "sort_order": 0 }],
  "type": "text|text_photo|text_code|text_code_photo",
  "is_published": true,
  "is_modified": false,
  "user": { "id": 1, "username": "...", "name": "...", "avatar_url": "...", "badge": "..." },
  "comments_count": 0,
  "likes_count": 0,
  "views_count": 0,
  "is_viewed": false,
  "is_liked_by_user": false,
  "tags": [{ "id": 1, "name": "php" }],
  "created_at": "YYYY-MM-DD HH:MM:SS",
  "updated_at": "YYYY-MM-DD HH:MM:SS"
}
```

## `Blog`

```json
{
  "id": 1,
  "title": "...",
  "subtitle": "...",
  "cover_image_url": "...",
  "reading_time": "5 min",
  "is_published": true,
  "is_modified": false,
  "user": { "id": 1, "username": "...", "name": "...", "avatar_url": "...", "badge": "..." },
  "comments_count": 0,
  "likes_count": 0,
  "is_liked_by_user": false,
  "tags": [{ "id": 1, "name": "php" }],
  "views_count": 0,
  "is_viewed": false,
  "sections": [{ "id": 1, "title": "...", "content": "...", "order": 1, "image_url": "..." }],
  "created_at": "ISO/DateTime",
  "updated_at": "ISO/DateTime"
}
```

## `Comment`

```json
{
  "id": 1,
  "body": "...",
  "type": "text|text_code",
  "code": "...",
  "code_language": "php",
  "parent_id": null,
  "has_childrens": false,
  "user_id": 1,
  "post_id": 10,
  "blog_id": null,
  "is_modified": false,
  "is_highlighted": false,
  "is_liked_by_user": false,
  "user_name": "username",
  "mentions": [{ "id": 2, "username": "alice", "profile_url": "..." }],
  "created_at": "YYYY-MM-DD HH:MM:SS",
  "updated_at": "YYYY-MM-DD HH:MM:SS"
}
```

## `Profile`

```json
{
  "username": "john",
  "name": "John",
  "email": "john@example.com",
  "avatar_url": "...",
  "bio": "...",
  "website": "...",
  "location": "...",
  "social_links": [],
  "cover_image_url": "...",
  "ranking_points": 0,
  "badge": "...",
  "posts_count": 0,
  "blogs_count": 0,
  "views_count": 0,
  "is_viewed": false,
  "followers_count": 0,
  "following_count": 0,
  "is_following": false,
  "joined_at": "ISO/DateTime",
  "last_seen_at": "ISO/DateTime|null",
  "tags": [{ "id": 1, "name": "php" }]
}
```

## `Notification`

```json
{
  "id": "uuid",
  "type": "...",
  "title": "...",
  "body": "...",
  "actor": {},
  "entity": {},
  "context": {},
  "read_at": "YYYY-MM-DD HH:MM:SS|null",
  "created_at": "YYYY-MM-DD HH:MM:SS"
}
```

## `Activity`

```json
{
  "id": 1,
  "action": "post_liked",
  "actor": { "id": 1, "username": "...", "name": "..." },
  "subject": { "type": "post", "id": 10 },
  "meta": {},
  "created_at": "YYYY-MM-DD HH:MM:SS"
}
```

## 4) Endpoint contract

Auth legend:
- `public`: no token required
- `auth`: Sanctum token required

## Authentication & account

- `POST /register` (`public`)
  - Body: `name`, `email`, `username`, `password`, `password_confirmation`, optional `bio`, `avatar`, `website`, `location`
  - `201`: `data.user`, `data.user_id`
- `POST /login` (`public`)
  - Body: `email`, `password`
  - `200`: `data.user`, `data.access_token`, `data.token_type`
- `POST /logout` (`auth`)
  - Body optional: `scope=current|all`, `all_devices:boolean`
  - `200`: no `data`
- `POST /otp/verify` (`public`)
  - Body: `email`, `code`
  - `200`: `data.user`, `data.access_token`, `data.token_type`
- `POST /otp/resend` (`public`)
  - Body: `email`, optional `channel=email|sms`
  - `200`: no `data`
- `POST /forgot-password` (`public`)
  - Body: `email`
  - `200`: generic success message
- `POST /reset-password` (`public`)
  - Body: `email`, `token`, `password`, `password_confirmation`
  - `200`: no `data`
- `POST /change-password` (`auth`)
  - Body: `current_password`, `password`, `password_confirmation`
  - `200`: no `data`
- `POST /change-name` (`auth`)
  - Body: `name`, `password`
  - `200`: `data.name`
- `POST /updateusername` (`auth`)
  - Body: `username`, `password`
  - `200`: `data` is `UserSummary`
- `POST /updateemail` (`auth`)
  - Body: `email`, `password`
  - `200`: no `data` (initiates OTP flow)

## Posts

- `GET /posts` (`public`) -> paginated `Post[]`
- `GET /posts/{post}` (`public`) -> `Post`
- `POST /posts` (`auth`)
  - Body: `title`, `body`, optional `code`, `code_language`, `photos[]`, `is_published`, `tags[]`
  - `201`: `Post`
- `PUT /posts/{post}/content` (`auth`)
  - Body any of: `title`, `body`, `code`, `code_language`, `is_published`, `tags[]`
  - `200`: `Post`
- `POST /posts/{post}/photos` (`auth`, multipart)
  - Body: `photo`
  - `200`: `Post`
- `PUT /posts/{post}/photos/{photo}` (`auth`, multipart)
  - Body: `photo`
  - `200`: `Post`
- `DELETE /posts/{post}/photos/{photo}` (`auth`) -> updated `Post`
- `DELETE /posts/{post}` (`auth`) -> success message
- `PUT /posts/{post}` (`auth`) -> intentionally deprecated, returns `404`
- `GET /posts/recommended` (`auth`)
  - Query optional: `page`, `per_page (1..30)`
  - `200`: paginated `Post[]`
- `GET /posts/drafts` (`auth`) -> paginated `Post[]`
- `GET /posts/viewers/{id}` (`auth`, owner only)
  - `200`: `data.viewers[]` (`id`, `username`, `viewed_at`)
- `POST /posts/{post}/toggle-like` (`auth`)
  - `200`: `data.is_liked`, `data.likes_count`

## Blogs

- `GET /blogs` (`public`) -> paginated `Blog[]`
- `GET /blogs/{blog}` (`public`) -> `Blog`
- `POST /blogs` (`auth`, multipart)
  - Body: `title`, `subtitle`, optional `reading_time`, optional `cover_image`, optional `tags[]`, `sections[]` (required)
  - `201`: `Blog`
- `PUT /blogs/{blog}` (`auth`, multipart/json)
  - Body any of: `title`, `subtitle`, `reading_time`, `tags[]`, `is_published`, `cover_image`, `remove_cover_image`
  - `200`: `Blog`
- `DELETE /blogs/{blog}` (`auth`) -> success message
- `POST /blogs/{blog}/toggle-like` (`auth`)
  - `200`: `data.is_liked`, `data.likes_count`
- `GET /blogs/drafts` (`auth`) -> paginated `Blog[]`
- `GET /blogs/viewers/{id}` (`auth`, owner only)
  - `200`: `data.viewers[]`

## Blog sections

- `POST /blogs/{blog}/sections` (`auth`, multipart/json)
  - Body: `title`, `content`, `order`, optional `image`
  - `201`: `Section`
- `PUT /blogs/{blog}/sections/{section}` (`auth`, multipart/json)
  - Body any of: `title`, `content`, `order`, `image`, `remove_image`
  - `200`: `Section`
- `DELETE /blogs/{blog}/sections/{section}` (`auth`) -> success message
- `PUT /blogs/{blog}/sections/reorder` (`auth`)
  - Body: `sections: [{id, order}, ...]` (must include every section exactly once)
  - `200`: `Blog`

## Comments

- `GET /posts/{post}/comments` (`public`) -> paginated `Comment[]` or empty success data
- `GET /blogs/{blog}/comments` (`public`) -> paginated `Comment[]` or empty success data
- `GET /comments/{comment}` (`auth`) -> `Comment`
- `POST /comments` (`auth`)
  - Body: `body`, exactly one of `post_id` or `blog_id`, optional `code`, `code_language`, `parent_id`
  - `201`: `Comment`
- `POST /comments/{comment}` (`auth`)
  - Body any of: `body`, `code`, `code_language`
  - `200`: `Comment`
- `POST /comments/{comment}/like` (`auth`) -> `data.likes`, `data.dislikes`, `data.is_liked_by_user`
- `POST /comments/{comment}/dislike` (`auth`) -> same shape as like
- `GET /comments/{comment}/children` (`auth`)
  - Query optional: `page`
  - `200`: `data.children[]`, `data.total_pages`
- `POST /comments/{comment}/highlight` (`auth`, content owner only)
  - `200`: `Comment`
- `DELETE /comments/{comment}` (`auth`) -> success message
- `POST /suggestions` (`auth`)
  - Body: `q` (min 2)
  - `200`: array of suggestion objects (`id`, `name`, `username`, `avatar`)

## Notifications & activity

- `GET /notifications` (`auth`) -> paginated `Notification[]`
- `GET /notifications/unread-count` (`auth`) -> `data.unread_count`
- `PATCH /notifications/{notification}/read` (`auth`) -> success message
- `PATCH /notifications/read-all` (`auth`) -> success message
- `GET /activity` (`auth`) -> paginated `Activity[]`

## Profile & users

- `GET /users/{username}` (`public`) -> `UserSummary`
- `GET /users/{username}/profile` (`public`) -> `Profile`
- `GET /users/{username}/posts` (`public`) -> paginated `Post[]`
- `GET /users/{username}/blogs` (`public`) -> paginated `Blog[]`
- `POST /users/{username}/follow` (`auth`) -> `data.is_following`, `data.followers_count`
- `DELETE /users/{username}/follow` (`auth`) -> same shape with `is_following=false`
- `GET /show-me` (`auth`) -> `Profile`
- `PUT /profile` (`auth`, multipart/json)
  - Body any of: `avatar`, `bio`, `website`, `location`, `social_links[]`, `tags[]`, `cover_image`, `settings`
  - `200`: `Profile`
- `GET /profiles` (`public`) -> paginated raw profile records
- `GET /profiles/{profile}` (`auth`) -> raw profile record with tags
- `GET /profiles/viewers/{id}` (`auth`, owner only) -> `data.viewers[]`

## Saved, views, tags, roadmaps

- `GET /saved` (`auth`)
  - Query: `type=post|blog|all`
  - `200`: paginated saved items (`kind`, `saved_at`, `data` -> `Post|Blog`)
- `POST /saves` (`auth`)
  - Body: `type=post|blog`, `id`
  - `200`: `data.saved`, `data.kind`, `data.saved_at`
- `DELETE /saves` (`auth`)
  - Body: `type=post|blog`, `id`
  - `200`: success message
- `POST /views` (`auth`)
  - Body: `type=post|blog|profile`, `id`
  - `200`: `data.view_recorded`, `data.already_viewed`, `data.type`, `data.id`, `data.views_count`
- `GET /tags` (`public`) -> `Tag[]`
- `POST /updatepost/tags/{post}` (`auth`) -> success message
- `POST /updateprofile/tags/{profile}` (`auth`) -> success message
- `POST /survy` (`auth`) -> success message
- `GET /roadmaps` (`public`) -> array of roadmaps
- `GET /roadmaps/{id}` (`public`) -> roadmap with `nodes`

## Utility/AI endpoints

- `POST /search` (`public`)
  - Body: `query`, optional `tab=posts|blogs|users`, optional `tags[]`, optional `page`
  - `200`: `data` list depends on `tab`
- `POST /analyze-code` (`public`)
  - Body: `code`
  - `200`: `data.label`, `data.explanation`, `data.raw`
- `POST /compile` (`public`)
  - Body: `language`, `code`, optional `input`
  - `200`: `data.result`
- `POST /generate-uml` (`public`)
  - Body: `description`
  - **Response is binary** (`image/png`), not JSON envelope

## 5) Frontend integration notes

- Always read `status` first on JSON endpoints.
- For paginated responses, use `pagination.has_more_pages` and `pagination.current_page`.
- Some endpoints return raw model arrays (for example `search` non-user tabs and `profiles/{id}`); treat these as server-defined objects, not resource-wrapped shapes.
- `PUT /posts/{post}` is deprecated and intentionally returns `404`.
- `POST /generate-uml` must be consumed as blob/image response.
