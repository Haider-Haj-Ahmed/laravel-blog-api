# Likes Feature - Postman Testing Guide

## Overview
This guide explains how to test the likes feature on your REST API using Postman.

## Database Setup
Make sure migrations are run:
```bash
php artisan migrate
```

The `likes` table includes:
- `id` (Primary Key)
- `user_id` (Foreign Key to users)
- `post_id` (Foreign Key to posts)
- `unique constraint` on (user_id, post_id) - prevents duplicate likes
- `timestamps` (created_at, updated_at)

---

## Prerequisites

### 1. Create Test Users
Register or login with at least 2 users to test the likes feature properly:

**Register User 1:**
```
POST /api/register
Content-Type: application/json

{
  "name": "User One",
  "email": "user1@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "username": "user_one",
  "phone": "+1234567890"
}
```

**Register User 2:**
```
POST /api/register
Content-Type: application/json

{
  "name": "User Two",
  "email": "user2@example.com",
  "password": "password123",
  "password_confirmation": "password123",
  "username": "user_two",
  "phone": "+0987654321"
}
```

### 2. Verify OTP (if phone verification is required)
```
POST /api/otp/verify
Content-Type: application/json

{
  "user_id": 1,
  "code": "175269"  // Use the OTP from your notification
}
```

**Expected Response:**
```json
{
  "data": {
    "user": {
      "id": 1,
      "name": "User One",
      "email": "user1@example.com",
      "username": "user_one",
      "phone": "+1234567890",
      "phone_verified_at": "2025-03-09 10:30:00",
      "email_verified_at": null,
      "created_at": "2025-03-09 10:30:00",
      "updated_at": "2025-03-09 10:30:00"
    },
    "access_token": "YOUR_AUTH_TOKEN_HERE",
    "token_type": "Bearer"
  },
  "message": "OTP verified successfully. You are now authenticated."
}
```

**Save the `access_token`** for subsequent requests.

### 3. Login to get Authorization Token
```
POST /api/login
Content-Type: application/json

{
  "email": "user1@example.com",
  "password": "password123"
}
```

**Response includes:**
```json
{
  "data": {
    "token": "YOUR_AUTH_TOKEN_HERE"
  },
  "message": "Login successful"
}
```

---

## Setting Up Postman for Authenticated Requests

For all authenticated endpoints, add this header:
```
Authorization: Bearer YOUR_AUTH_TOKEN_HERE
```

Or in Postman:
1. Go to the request settings
2. Click "Authorization" tab
3. Select "Bearer Token" type
4. Paste your token

---

## Testing Likes Feature

### Step 1: Create a Post
**POST** `/api/posts`

Headers:
```
Authorization: Bearer USER1_TOKEN
Content-Type: application/json
```

Body:
```json
{
  "title": "My First Post",
  "body": "This is a test post for likes feature",
  "is_published": true
}
```

**Expected Response:**
```json
{
  "data": {
    "id": 1,
    "title": "My First Post",
    "body": "This is a test post for likes feature",
    "is_published": true,
    "user": {
      "id": 1,
      "username": "user_one",
      "name": "User One"
    },
    "comments_count": 0,
    "likes_count": 0,
    "is_liked_by_user": false,
    "created_at": "2025-03-09 10:30:00",
    "updated_at": "2025-03-09 10:30:00"
  },
  "message": "Post created successfully"
}
```

**Save the post ID** for subsequent tests.

---

### Step 2: View All Posts (with like status)
**GET** `/api/posts`

Headers:
```
Authorization: Bearer USER1_TOKEN
```

**Expected Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "My First Post",
      "body": "This is a test post for likes feature",
      "is_published": true,
      "user": {
        "id": 1,
        "username": "user_one",
        "name": "User One"
      },
      "comments_count": 0,
      "likes_count": 0,
      "is_liked_by_user": false,
      "created_at": "2025-03-09 10:30:00",
      "updated_at": "2025-03-09 10:30:00"
    }
  ],
  "message": "Posts retrieved successfully",
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

**Note:** The `is_liked_by_user` field will be `false` initially for posts not liked by the current user.

---

### Step 3: Like a Post (Toggle Like - First Click)
**POST** `/api/posts/{post_id}/toggle-like`

Example: `POST /api/posts/1/toggle-like`

Headers:
```
Authorization: Bearer USER1_TOKEN
```

**Expected Response:**
```json
{
  "data": {
    "is_liked": true,
    "likes_count": 1
  },
  "message": "Post liked"
}
```

---

### Step 4: Unlike a Post (Toggle Like - Second Click)
**POST** `/api/posts/{post_id}/toggle-like`

Example: `POST /api/posts/1/toggle-like`

Headers:
```
Authorization: Bearer USER1_TOKEN
```

**Expected Response:**
```json
{
  "data": {
    "is_liked": false,
    "likes_count": 0
  },
  "message": "Post unliked"
}
```

---

### Step 5: Multiple Users Liking Same Post
Switch to USER2's token and like the same post:

**POST** `/api/posts/1/toggle-like`

Headers:
```
Authorization: Bearer USER2_TOKEN
```

**User 2 Response:**
```json
{
  "data": {
    "is_liked": true,
    "likes_count": 1
  },
  "message": "Post liked"
}
```

Now if User 1 checks the post:

**GET** `/api/posts/1`

Headers:
```
Authorization: Bearer USER1_TOKEN
```

**Response:**
```json
{
  "data": {
    "id": 1,
    "title": "My First Post",
    "body": "This is a test post for likes feature",
    "is_published": true,
    "user": {
      "id": 1,
      "username": "user_one",
      "name": "User One"
    },
    "comments_count": 0,
    "likes_count": 1,
    "is_liked_by_user": false,  // User 1 did not like it
    "created_at": "2025-03-09 10:30:00",
    "updated_at": "2025-03-09 10:30:00"
  },
  "message": "Post retrieved successfully"
}
```

---

### Step 6: View Public Posts (without authentication)
**GET** `/api/posts`

**Expected Response:**
```json
{
  "data": [
    {
      "id": 1,
      "title": "My First Post",
      "body": "This is a test post for likes feature",
      "is_published": true,
      "user": {
        "id": 1,
        "username": "user_one",
        "name": "User One"
      },
      "comments_count": 0,
      "likes_count": 1,
      "is_liked_by_user": null,  // Not authenticated, so null
      "created_at": "2025-03-09 10:30:00",
      "updated_at": "2025-03-09 10:30:00"
    }
  ],
  "message": "Posts retrieved successfully",
  "pagination": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

---

## Error Scenarios

### 1. Like a Non-existent Post
**POST** `/api/posts/999/toggle-like`

Headers:
```
Authorization: Bearer USER1_TOKEN
```

**Expected Response (404):**
```json
{
  "message": "Post not found",
  "status_code": 404
}
```

---

### 2. Try to Like Without Authentication
**POST** `/api/posts/1/toggle-like`

**Expected Response (401):**
```json
{
  "message": "Unauthenticated",
  "status_code": 401
}
```

---

### 3. Prevent Duplicate Likes (Database Constraint)
The database has a unique constraint on `(user_id, post_id)`. If somehow duplicate creation is attempted, the database will prevent it automatically.

---

## Key Features to Verify

✅ **Toggle Functionality:**
- Like increases count from 0 to 1
- Unlike decreases count from 1 to 0
- Multiple toggles work correctly

✅ **Multiple Users:**
- Different users can like the same post
- Each user's like is counted independently
- `is_liked_by_user` is accurate per user

✅ **Post Listing:**
- Like count is included in post listings
- `is_liked_by_user` status is correct for authenticated users
- Public posts show `null` for `is_liked_by_user` when not authenticated

✅ **Authentication:**
- Like endpoints require authentication token
- OTP verification may be required (check middleware)
- Proper error messages for unauthorized access

✅ **Data Integrity:**
- Likes are properly associated with users and posts
- Unique constraint prevents duplicate likes
- Cascade delete removes likes when user or post is deleted

---

## Postman Collection Import (Optional)

If you want to automate these tests, create a Postman collection with environment variables:

```json
{
  "info": {
    "name": "Blog REST API - Likes Testing",
    "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
  },
  "variable": [
    {
      "key": "base_url",
      "value": "http://localhost:8000/api"
    },
    {
      "key": "user1_token",
      "value": ""
    },
    {
      "key": "user2_token",
      "value": ""
    },
    {
      "key": "post_id",
      "value": ""
    }
  ]
}
```

---

## Troubleshooting

### Issue: "Unauthenticated" Error
- Make sure you're using the token from the login response
- Prefix token with "Bearer " in the Authorization header
- Token might be expired; generate a new one by logging in again

### Issue: "Post not found" Error
- Verify the post ID exists in the database
- Try listing all posts first to get valid IDs

### Issue: OTP Verification Required
- If middleware requires phone verification, make sure to verify OTP before accessing protected endpoints
- Check the `verified.otp` middleware in routes

### Issue: Likes Not Incrementing
- Refresh the page after updating (GET request)
- Check that the user is logged in with a valid token
- Verify database migration was run successfully

---

## Success Checklist

- [ ] Database migrations are run
- [ ] At least 2 test users are created
- [ ] Auth tokens are obtained for both users
- [ ] Posts can be created
- [ ] Likes can be toggled successfully
- [ ] Like count updates correctly
- [ ] `is_liked_by_user` shows correct status
- [ ] Multiple users can like the same post
- [ ] Error handling works for invalid posts
- [ ] Authentication is required for like endpoints
