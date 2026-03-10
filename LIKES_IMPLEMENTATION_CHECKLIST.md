# Likes Feature - Implementation Checklist & Testing Guide

## Implementation Status

✅ **Database Layer**
- [x] Migration created: `2025_11_30_000000_create_likes_table.php`
- [x] Likes table has proper foreign keys with cascade delete
- [x] Unique constraint on (user_id, post_id) prevents duplicate likes
- [x] Timestamps included for like records

✅ **Model Layer**
- [x] Like model exists with relationships to User and Post
- [x] Post model has `likes()` relationship (hasMany)
- [x] Post model has `likedByUsers()` relationship (belongsToMany)
- [x] Post model has helper method `isLikedBy($user)`
- [x] User model has `likes()` relationship
- [x] User model has `likedPosts()` relationship

✅ **API Layer**
- [x] PostController has `toggleLike($postId)` method
- [x] Route defined: `POST /api/posts/{post}/toggle-like`
- [x] Route is protected by auth:sanctum middleware
- [x] Route may require verified.otp middleware (check routes/api.php)

✅ **Response Layer**
- [x] PostResource includes likes_count
- [x] PostResource includes is_liked_by_user status
- [x] API responses use ApiResponseTrait for consistent formatting
- [x] Error responses return proper HTTP status codes

---

## Pre-Testing Requirements

### 1. Environment Setup
```bash
# Install dependencies
composer install
npm install

# Set up environment file
cp .env.example .env
php artisan key:generate

# Configure database
# Edit .env and set up database connection
# Example for SQLite:
# DB_CONNECTION=sqlite
# DB_DATABASE=database/database.sqlite
```

### 2. Database Setup
```bash
# Run migrations
php artisan migrate

# Optional: Seed test data if seeders are configured
php artisan db:seed
```

### 3. Start Development Server
```bash
# Option 1: Using PHP artisan
php artisan serve
# Server runs on http://localhost:8000

# Option 2: Using Laravel Valet (if installed)
valet serve

# Option 3: Using Docker
docker-compose up
```

---

## Postman Testing Flow

### Step 1: Import Environment & Collection
1. Open Postman
2. Click "Import" → "Upload Files"
3. Select `postman_environment.json` and `postman_collection.json`
4. In Postman, select the environment from the top-right dropdown

### Step 2: Register Users
1. Run: **Auth → Register User 1**
   - Response should be 201 Created
   - User 1 is created with email: `user1@example.com`

2. Run: **Auth → Register User 2**
   - Response should be 201 Created
   - User 2 is created with email: `user2@example.com`

### Step 3: Handle OTP (if required)
- After registration, check your email/logs for OTP code
- Set the OTP code in Postman environment variables (`user1_otp`, `user2_otp`)
- Run: **Auth → Verify OTP User 1** or **Verify OTP User 2**
- **OTP verification automatically returns an authentication token**
- Token is automatically saved to environment variables via Postman tests

### Step 4: Create a Post
1. Run: **Posts → Create Post (User 1)**
   - Response should be 201 Created
   - Response includes post details with `likes_count: 0` and `is_liked_by_user: false`
   - Post ID is automatically saved to `post_id` environment variable

### Step 5: Test Likes Functionality
   - Response includes post details with `likes_count: 0` and `is_liked_by_user: false`
   - Post ID is automatically saved to `post_id` environment variable

### Step 6: Test Likes Functionality
1. Run: **Likes Feature → Like Post (User 1)**
   - Expected: `is_liked: true`, `likes_count: 1`
   - Response: `{"data": {"is_liked": true, "likes_count": 1}, "message": "Post liked"}`

2. Run: **Likes Feature → Get Post After Likes**
   - User 1 should see `is_liked_by_user: true`
   - Like count should show 1

3. Run: **Likes Feature → Like Post (User 2)**
   - User 2 executes toggle-like on the same post
   - Expected: `is_liked: true`, `likes_count: 2`

4. Run: **Likes Feature → Get Post After Likes**
   - Switch authorization header to User 2's token
   - User 2 should see `is_liked_by_user: true`
   - Like count should show 2

5. Run: **Likes Feature → Unlike Post (User 1)**
   - Toggle-like removes the like
   - Expected: `is_liked: false`, `likes_count: 1`

6. Run: **Likes Feature → Get All Posts (User 1 Authenticated)**
   - Should show post with updated like count
   - `is_liked_by_user` should reflect current user's like status

### Step 7: Test Error Cases
1. Run: **Error Tests → Like Non-existent Post**
   - Should return 404 with message "Post not found"

2. Run: **Error Tests → Like Without Authentication**
   - Should return 401 with message "Unauthenticated"

---

## Response Examples

### Success: Like a Post
```json
HTTP/1.1 200 OK

{
  "data": {
    "is_liked": true,
    "likes_count": 1
  },
  "message": "Post liked"
}
```

### Success: Unlike a Post
```json
HTTP/1.1 200 OK

{
  "data": {
    "is_liked": false,
    "likes_count": 0
  },
  "message": "Post unliked"
}
```

### Success: Get Post with Like Status
```json
HTTP/1.1 200 OK

{
  "data": {
    "id": 1,
    "title": "My First Post",
    "body": "This is a test post",
    "is_published": true,
    "user": {
      "id": 1,
      "username": "user_one",
      "name": "User One"
    },
    "comments_count": 0,
    "likes_count": 2,
    "is_liked_by_user": true,
    "created_at": "2025-03-09 10:30:00",
    "updated_at": "2025-03-09 10:30:00"
  },
  "message": "Post retrieved successfully"
}
```

### Error: Post Not Found
```json
HTTP/1.1 404 Not Found

{
  "message": "Post not found",
  "status_code": 404
}
```

### Error: Unauthenticated
```json
HTTP/1.1 401 Unauthorized

{
  "message": "Unauthenticated",
  "status_code": 401
}
```

---

## Key Features to Verify

### 1. Toggle Functionality
- [ ] Like post increases count
- [ ] Unlike post decreases count
- [ ] Toggle works multiple times
- [ ] State persists in database

### 2. Multiple Users
- [ ] Different users can like same post
- [ ] Each user's like is counted separately
- [ ] `is_liked_by_user` is accurate per user
- [ ] User A liking doesn't affect User B's `is_liked_by_user` status

### 3. Counts & Consistency
- [ ] `likes_count` increments correctly
- [ ] `likes_count` decrements correctly
- [ ] Public posts show correct count without authentication
- [ ] Authenticated users see correct count and personal like status

### 4. Authentication & Authorization
- [ ] Only authenticated users can like posts
- [ ] Users cannot like posts anonymously
- [ ] Auth token validates correctly
- [ ] Expired tokens are rejected

### 5. Data Integrity
- [ ] Database unique constraint prevents duplicate likes
- [ ] Liking same post twice is prevented (or properly toggled)
- [ ] Like records are properly associated with users and posts
- [ ] Cascade delete works: deleting post removes its likes

### 6. Edge Cases
- [ ] Cannot like non-existent post
- [ ] Cannot like with invalid user ID
- [ ] Like toggle on deleted post returns 404
- [ ] Public endpoint shows null/false for `is_liked_by_user` when not authenticated

---

## Troubleshooting Guide

### Issue: "Unauthenticated" Error on Like
**Cause:** Missing or invalid auth token

**Solution:**
1. Verify you're logged in by checking the auth token exists
2. Copy the token from login response
3. Add `Authorization: Bearer {token}` header
4. In Postman: Use Authorization tab → Bearer Token type

### Issue: "Post not found" Error
**Cause:** Invalid post ID or post doesn't exist

**Solution:**
1. Create a post first
2. Copy the post ID from the response
3. Use that ID in the toggle-like endpoint
4. Verify post exists by calling GET /posts/{post_id}

### Issue: Like Count Not Updating
**Cause:** Not refreshing the data or like not being saved

**Solution:**
1. Make a fresh GET request to the post endpoint
2. Check database to verify like record exists
3. Verify authentication token is valid and belongs to correct user
4. Check server logs for any errors

### Issue: OTP Verification Required
**Cause:** API requires phone verification before accessing endpoints

**Solution:**
1. After registration, you'll receive OTP via email (check logs or email)
2. Set the OTP code in Postman environment: `user1_otp` or `user2_otp`
3. Call `POST /api/otp/verify` with `user_id` and `code`
4. OTP verification returns an authentication token automatically
5. Use this token for subsequent API calls

### Issue: Cascading Deletes Not Working
**Cause:** Database constraint issues or migration didn't run properly

**Solution:**
1. Run: `php artisan migrate:fresh` (careful: this drops all data)
2. Verify migration file has `onDelete('cascade')`
3. Check database that foreign keys are properly created
4. Re-create test data

---

## Performance Tips

1. **Optimize Queries:**
   - Use `withCount('likes')` when loading multiple posts
   - Use `with('user')` to avoid N+1 queries
   - Consider caching popular posts

2. **Database:**
   - Ensure proper indexes on user_id and post_id
   - Check query performance with Laravel Debugbar

3. **API Response:**
   - Include `likes_count` in post listings
   - Cache-bust timestamps when like count changes

---

## Files Reference

| File | Purpose |
|------|---------|
| [app/Models/Like.php](app/Models/Like.php) | Like model with relationships |
| [app/Models/Post.php](app/Models/Post.php) | Post model with like relationships |
| [app/Models/User.php](app/Models/User.php) | User model with like relationships |
| [app/Http/Controllers/API/PostController.php](app/Http/Controllers/API/PostController.php) | PostController with toggleLike method |
| [app/Http/Resources/PostResource.php](app/Http/Resources/PostResource.php) | PostResource with like status |
| [database/migrations/2025_11_30_000000_create_likes_table.php](database/migrations/2025_11_30_000000_create_likes_table.php) | Likes table migration |
| [routes/api.php](routes/api.php) | API routes including toggle-like |
| [postman_collection.json](postman_collection.json) | Postman collection for testing |
| [postman_environment.json](postman_environment.json) | Postman environment configuration |

---

## Database Schema

```sql
CREATE TABLE likes (
  id BIGINT UNSIGNED PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  post_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP,
  updated_at TIMESTAMP,
  UNIQUE KEY unique_user_post (user_id, post_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);
```

---

## Success Criteria

Before marking the likes feature as complete:

- [ ] All test cases in "Postman Testing Flow" pass
- [ ] All "Key Features to Verify" checkboxes are checked
- [ ] Database contains like records with proper relationships
- [ ] `likes_count` accurately reflects number of likes
- [ ] `is_liked_by_user` correctly shows user's like status
- [ ] Toggle functionality works without errors
- [ ] Multiple users can like the same post
- [ ] Error handling returns proper HTTP status codes
- [ ] No SQL errors in server logs
- [ ] API responses match documented format

---

## Notes

- The likes feature uses toggle functionality (Instagram-style)
- One click likes the post, another click unlikes it
- Each user can only like a post once (enforced by unique constraint)
- Likes are deleted when the user or post is deleted (cascade delete)
- The `is_liked_by_user` field requires authentication to determine personal status
