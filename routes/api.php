<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\PostController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('api.auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    Route::get('/feed', FeedController::class)->name('api.feed');

    Route::get('/posts/{post}', [PostController::class, 'show'])->name('api.posts.show');
    Route::post('/posts', [PostController::class, 'store'])->name('api.posts.store');
    Route::delete('/posts/{post}', [PostController::class, 'destroy'])->name('api.posts.destroy');

    Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('api.comments.store');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('api.comments.destroy');
});
