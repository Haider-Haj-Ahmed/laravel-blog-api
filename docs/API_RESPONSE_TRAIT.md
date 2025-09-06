# API Response Trait Documentation

## Overview

The `ApiResponseTrait` provides a unified way to format JSON responses across your Laravel API. This ensures consistency and makes your API more professional and predictable for frontend developers.

## Features

- ✅ **Consistent Response Format**: All responses follow the same structure
- ✅ **HTTP Status Codes**: Proper status codes for different scenarios
- ✅ **Pagination Support**: Built-in pagination response format
- ✅ **Error Handling**: Standardized error responses
- ✅ **Clean Code**: Reduces boilerplate code in controllers

## Response Format

### Success Response
```json
{
    "status": "success",
    "message": "Operation completed successfully",
    "data": {
        // Your data here
    }
}
```

### Error Response
```json
{
    "status": "error",
    "message": "Error description",
    "errors": {
        // Validation errors (optional)
    }
}
```

### Paginated Response
```json
{
    "status": "success",
    "message": "Data retrieved successfully",
    "data": [
        // Your paginated data
    ],
    "pagination": {
        "current_page": 1,
        "last_page": 5,
        "per_page": 15,
        "total": 75,
        "from": 1,
        "to": 15,
        "has_more_pages": true
    }
}
```

## Available Methods

### `successResponse($data, $message, $code)`
Returns a successful response with data.

**Parameters:**
- `$data` (mixed): The response data
- `$message` (string|null): Success message
- `$code` (int): HTTP status code (default: 200)

**Example:**
```php
return $this->successResponse($user, 'User retrieved successfully');
```

### `errorResponse($message, $code, $errors)`
Returns an error response.

**Parameters:**
- `$message` (string): Error message
- `$code` (int): HTTP status code (default: 400)
- `$errors` (mixed): Additional error details (optional)

**Example:**
```php
return $this->errorResponse('Something went wrong', 500);
```

### `validationErrorResponse($errors, $message)`
Returns a validation error response.

**Parameters:**
- `$errors` (mixed): Validation errors
- `$message` (string): Error message (default: 'Validation failed')

**Example:**
```php
return $this->validationErrorResponse($validator->errors(), 'Invalid input data');
```

### `notFoundResponse($message)`
Returns a 404 not found response.

**Parameters:**
- `$message` (string): Error message (default: 'Resource not found')

**Example:**
```php
return $this->notFoundResponse('User not found');
```

### `unauthorizedResponse($message)`
Returns a 401 unauthorized response.

**Parameters:**
- `$message` (string): Error message (default: 'Unauthorized')

**Example:**
```php
return $this->unauthorizedResponse('Invalid credentials');
```

### `forbiddenResponse($message)`
Returns a 403 forbidden response.

**Parameters:**
- `$message` (string): Error message (default: 'Forbidden')

**Example:**
```php
return $this->forbiddenResponse('You are not authorized to perform this action');
```

### `createdResponse($data, $message)`
Returns a 201 created response.

**Parameters:**
- `$data` (mixed): The created resource data
- `$message` (string): Success message (default: 'Resource created successfully')

**Example:**
```php
return $this->createdResponse($post, 'Post created successfully');
```

### `noContentResponse($message)`
Returns a 204 no content response.

**Parameters:**
- `$message` (string): Success message (default: 'Operation completed successfully')

**Example:**
```php
return $this->noContentResponse('Resource deleted successfully');
```

### `paginatedResponse($data, $message)`
Returns a paginated response.

**Parameters:**
- `$data` (LengthAwarePaginator): Paginated data
- `$message` (string|null): Success message

**Example:**
```php
$posts = Post::paginate(15);
return $this->paginatedResponse($posts, 'Posts retrieved successfully');
```

## Usage in Controllers

### 1. Add the Trait
```php
<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;

class PostController extends Controller
{
    use ApiResponseTrait;
    
    // Your controller methods...
}
```

### 2. Use in Methods
```php
public function index()
{
    $posts = Post::with('user')->paginate(15);
    
    return $this->paginatedResponse($posts, 'Posts retrieved successfully');
}

public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'body' => 'required|string',
    ]);

    $post = Post::create($validated);

    return $this->createdResponse($post, 'Post created successfully');
}

public function show($id)
{
    $post = Post::find($id);

    if (!$post) {
        return $this->notFoundResponse('Post not found');
    }

    return $this->successResponse($post, 'Post retrieved successfully');
}

public function update(Request $request, $id)
{
    $post = Post::find($id);

    if (!$post) {
        return $this->notFoundResponse('Post not found');
    }

    if ($request->user()->id !== $post->user_id) {
        return $this->forbiddenResponse('You are not authorized to update this post');
    }

    $validated = $request->validate([
        'title' => 'sometimes|string|max:255',
        'body' => 'sometimes|string',
    ]);

    $post->update($validated);

    return $this->successResponse($post, 'Post updated successfully');
}

public function destroy(Request $request, $id)
{
    $post = Post::find($id);

    if (!$post) {
        return $this->notFoundResponse('Post not found');
    }

    if ($request->user()->id !== $post->user_id) {
        return $this->forbiddenResponse('You are not authorized to delete this post');
    }

    $post->delete();

    return $this->successResponse(null, 'Post deleted successfully');
}
```

## Best Practices

### 1. Always Use the Trait
Use the trait in all your API controllers to maintain consistency.

### 2. Provide Meaningful Messages
Always provide clear, descriptive messages for both success and error responses.

### 3. Use Appropriate HTTP Status Codes
- `200` - Success
- `201` - Created
- `204` - No Content
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `422` - Validation Error
- `500` - Server Error

### 4. Handle Validation Errors
```php
public function store(Request $request)
{
    try {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
    } catch (ValidationException $e) {
        return $this->validationErrorResponse($e->errors(), 'Validation failed');
    }

    // Continue with your logic...
}
```

### 5. Use Pagination for Lists
Always paginate large datasets to improve performance and user experience.

```php
public function index()
{
    $posts = Post::with('user')->paginate(15);
    return $this->paginatedResponse($posts);
}
```

## Migration from Old Responses

### Before (Inconsistent)
```php
// Different formats across controllers
return response()->json(['data' => $posts]);
return response()->json($post, 201);
return response()->json(['message' => 'Error'], 404);
```

### After (Consistent)
```php
// Unified format using the trait
return $this->successResponse($posts, 'Posts retrieved successfully');
return $this->createdResponse($post, 'Post created successfully');
return $this->notFoundResponse('Post not found');
```

## Benefits

1. **Consistency**: All API responses follow the same format
2. **Maintainability**: Easy to update response format across the entire API
3. **Developer Experience**: Frontend developers know what to expect
4. **Error Handling**: Standardized error responses
5. **Pagination**: Built-in pagination support
6. **Clean Code**: Reduces boilerplate code in controllers

## Conclusion

The `ApiResponseTrait` is a powerful tool that helps maintain consistency and professionalism in your Laravel API. By using this trait across all your controllers, you ensure that your API responses are predictable and easy to work with for frontend developers.
