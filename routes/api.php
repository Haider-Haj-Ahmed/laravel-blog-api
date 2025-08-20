<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\PostController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Auth;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // كل عمليات الإنشاء والتعديل والحذف محمية
    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{id}', [PostController::class, 'update']);
    Route::delete('/posts/{id}', [PostController::class, 'destroy']);
});

// عرض عام (index/show) بدون تسجيل دخول
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{id}', [PostController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{id}', [CommentController::class, 'update']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);
});

Route::get('/posts/{post}/comments', [CommentController::class, 'index']);


Route::get('/users/{username}', [UserController::class, 'showByUsername']);

Route::middleware('auth:sanctum')->get('/notifications', function () {
    return Auth::user()->notifications;
});
