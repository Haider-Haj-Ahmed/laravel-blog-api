# Postman Likes Testing Guide

Updated for current API behavior in `routes/api.php` and resource/controller output.

## Endpoints under test

- Post likes: `POST /api/posts/{post}/toggle-like`
- Blog likes: `POST /api/blogs/{blog}/toggle-like`

Both require `Authorization: Bearer <token>`.

## Prerequisites

1. Create two users.
2. Verify OTP for both (`POST /api/otp/verify`) or login with already verified accounts.
3. Save both bearer tokens in Postman variables.

## Core flow (posts)

### 1) Create a published post as User A

`POST /api/posts`

Body example:

```json
{
  "title": "Like test",
  "body": "Testing toggle",
  "is_published": true
}
```

Save `data.id` as `post_id`.

### 2) Like as User A

`POST /api/posts/{{post_id}}/toggle-like`

Expected body includes:

```json
{
  "status": "success",
  "message": "Post liked",
  "data": {
    "is_liked": true,
    "likes_count": 1
  }
}
```

### 3) Toggle again as User A (unlike)

Expected:

```json
{
  "status": "success",
  "message": "Post unliked",
  "data": {
    "is_liked": false
  }
}
```

(`likes_count` should decrease accordingly.)

### 4) Like as User B

Call the same endpoint with User B token and verify count/user-specific behavior.

### 5) Verify post details

`GET /api/posts/{{post_id}}` as each user:

- User who liked should see `is_liked_by_user: true`
- User who did not like should see `is_liked_by_user: false`

### 6) Verify unauthenticated behavior

`GET /api/posts/{{post_id}}` without token currently returns `is_liked_by_user: false`.

## Error checks

### Not found

`POST /api/posts/999999/toggle-like`

Expected shape:

```json
{
  "status": "error",
  "message": "Post not found"
}
```

### Unauthenticated

`POST /api/posts/{{post_id}}/toggle-like` without token should return 401 (`Unauthenticated`).

## Blog parity check

Repeat same toggle checks with:

- `POST /api/blogs/{blog}/toggle-like`

Expected data keys are the same: `is_liked`, `likes_count`.
