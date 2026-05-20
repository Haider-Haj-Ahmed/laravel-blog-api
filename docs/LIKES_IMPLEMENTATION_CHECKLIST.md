# Likes Feature Checklist

This checklist reflects current behavior in `routes/api.php`, `PostController`, `BlogController`, and related resources.

## Routes

- [x] `POST /api/posts/{post}/toggle-like` (auth required)
- [x] `POST /api/blogs/{blog}/toggle-like` (auth required)

## Current response contract

Post/blog toggle like endpoints use `ApiResponseTrait` success shape:

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

Common error shape for trait-based errors:

```json
{
  "status": "error",
  "message": "Post not found"
}
```

## Resource expectations

- `PostResource` and `BlogResource` expose `likes_count`.
- `PostResource` and `BlogResource` expose `is_liked_by_user`.
- For unauthenticated requests to public content, `is_liked_by_user` currently resolves to `false`.

## Functional checklist

### Toggle behavior

- [ ] First call likes the content (`is_liked = true`)
- [ ] Second call unlikes the content (`is_liked = false`)
- [ ] `likes_count` increments/decrements correctly

### Multi-user behavior

- [ ] Two users can like the same post/blog
- [ ] `likes_count` aggregates both users
- [ ] `is_liked_by_user` is user-specific

### Auth and errors

- [ ] Unauthenticated toggle-like returns 401
- [ ] Non-existing post/blog returns 404
- [ ] Invalid token returns 401

### Data integrity

- [ ] One like per user per content item
- [ ] Like rows are deleted on parent/user delete (cascade)

## Quick Postman flow

1. Register and verify OTP for User A and User B.
2. User A creates a published post.
3. User A toggles like on that post.
4. User B toggles like on the same post.
5. Fetch `GET /api/posts/{post}` as each user and compare `is_liked_by_user`.
6. Toggle again to validate unlike behavior.

## Related files

- `app/Http/Controllers/API/PostController.php`
- `app/Http/Controllers/API/BlogController.php`
- `app/Http/Resources/PostResource.php`
- `app/Http/Resources/BlogResource.php`
- `routes/api.php`
