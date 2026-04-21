<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CompilerController;
use App\Http\Controllers\API\ActivityController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\API\OtpController;
use App\Http\Controllers\API\RoadMapController;
use App\Http\Controllers\API\ProfileController;
use App\Http\Controllers\API\BlogController;
use App\Http\Controllers\API\TagController;
use App\Http\Controllers\API\SavedController;
use App\Http\Controllers\API\SearchController;
use App\Http\Controllers\API\ViewController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('throttle:10,1');
Route::post('/otp/resend', [OtpController::class, 'resend'])->middleware('throttle:5,10');

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Posts
    Route::post('/posts', [PostController::class, 'store']);
    Route::get('/posts/recommended', [PostController::class, 'recommended'])->middleware('throttle:recommended-feed');
    Route::get('/posts/drafts', [PostController::class, 'drafts']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);
    Route::get('/posts/viewers/{id}', [PostController::class, 'viewrs']);


    // Likes (Instagram-style toggle)
    Route::post('/posts/{post}/toggle-like', [PostController::class, 'toggleLike']);
    Route::post('/blogs/{blog}/toggle-like', [BlogController::class, 'toggleLike']);

    // Comments
    Route::post('/comments', [CommentController::class, 'store']);
    // Route::post('/blogs/{blog}/comments', [CommentController::class, 'storeForBlog']);
    Route::get('/comments/{comment}', [CommentController::class, 'show']);
    Route::post('/comments/{comment}', [CommentController::class, 'update']);
    Route::post('/comments/{comment}/like', [CommentController::class, 'like']);
    Route::post('/comments/{comment}/dislike', [CommentController::class, 'dislike']);
    Route::get('/comments/{comment}/children',[CommentController::class,'getChildren']);
    Route::post('/comments/{comment}/highlight', [CommentController::class, 'highlight']);
    // Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::get('/activity', [ActivityController::class, 'index']);

    // Blogs
    Route::get('/blogs/viewers/{id}', [BlogController::class, 'viewrs']);
    Route::get('/blogs/drafts', [BlogController::class, 'drafts']);
    Route::apiResource('/blogs', BlogController::class);

    // Following
    Route::post('/users/{username}/follow', [UserController::class, 'follow']);
    Route::delete('/users/{username}/follow', [UserController::class, 'unfollow']);

    // Saved (bookmarks): polymorphic posts & blogs; extend morph map for more kinds later
    Route::get('/saved', [SavedController::class, 'index']);
    Route::post('/saves', [SavedController::class, 'store']);
    Route::delete('/saves', [SavedController::class, 'destroy']);

    // Views (polymorphic posts, blogs, profiles)
    Route::post('/views', [ViewController::class, 'store']);

    // Profile
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::get('/profiles/{profile}',[ProfileController::class,'showViaId']);
    // some tags routes
    Route::post('/updatepost/tags/{post}',[TagController::class,'updatePost']);
    Route::post('/updateprofile/tags/{profile}',[TagController::class,'updateProfile']);
    Route::get('/profiles/viewers/{id}', [ProfileController::class, 'viewrs']);
    Route::post('/survy',[TagController::class,'survy']);
});

// Public routes
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);
Route::get('/posts/{post}/comments', [CommentController::class, 'index']);
Route::get('/blogs/{blog}/comments', [CommentController::class, 'indexByBlog']);
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
//Tags Routes
Route::get('/tags',[TagController::class,'index']);
Route::get('/profiles',[ProfileController::class,'index']);
//Search
Route::post('/search',[SearchController::class,'search']);
//Suggetions
Route::post('/suggestions',[CommentController::class,'suggest'])->middleware('auth:sanctum');
