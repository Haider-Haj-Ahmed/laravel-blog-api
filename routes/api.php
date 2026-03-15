<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CompilerController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\API\OtpController;
use App\Http\Controllers\API\RoadMapController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\BlogController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/otp/verify', [OtpController::class, 'verify']);
Route::post('/otp/resend', [OtpController::class, 'resend']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Posts
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    // Likes (Instagram-style toggle)
    Route::post('/posts/{post}/toggle-like', [PostController::class, 'toggleLike']);

    // Comments
    Route::post('/comments', [CommentController::class, 'store']);
    Route::get('/comments/{comment}', [CommentController::class, 'show']);
    Route::post('/comments/{comment}', [CommentController::class, 'update']);
    Route::post('/comments/{comment}/like', [CommentController::class, 'like']);
    Route::post('/comments/{comment}/dislike', [CommentController::class, 'dislike']);
    Route::get('/comments/{comment}/children',[CommentController::class,'getChildren']);
    Route::post('/comments/{comment}/highlight', [CommentController::class, 'highlight']);
    // Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // Blogs
    Route::apiResource('/blogs', BlogController::class);

    // Profile
    Route::put('/profile', [ProfileController::class, 'update']);
});

// Public routes
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
Route::get('/users/{username}', [UserController::class, 'showByUsername']);
Route::get('/users/{username}/profile', [ProfileController::class, 'show']);
Route::get('/users/{username}/posts', [ProfileController::class, 'posts']);
Route::get('/users/{username}/blogs', [ProfileController::class, 'blogs']);
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{blog}', [BlogController::class, 'show']);

// Code analysis route for testing only
Route::post('/analyze-code', [App\Http\Controllers\API\CodeAnalysisController::class, 'analyze']);
//Compiler
Route::post('/compile',[CompilerController::class,'run']);
//UML Generator
Route::post('/generate-uml',[App\Http\Controllers\API\UMLController::class,'generate']);
//Road Map Routes
Route::get('/roadmaps',[RoadMapController::class,'index']);
Route::get('/roadmaps/{id}',[RoadMapController::class,'show']);