<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\Admin\SubmissionModerationController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SubmissionController::class, 'create'])->name('submit');
Route::post('/submissions', [SubmissionController::class, 'store'])
    ->middleware('throttle:submission')
    ->name('submissions.store');
Route::get('/media/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');
Route::get('/chat', [ChatController::class, 'index'])->name('chat');
Route::get('/chat/messages', [ChatController::class, 'fetch'])->name('chat.messages.index');
Route::post('/chat/messages', [ChatController::class, 'store'])
    ->middleware('throttle:chat-message')
    ->name('chat.messages.store');
Route::get('/wall', [SubmissionController::class, 'wall'])->name('wall');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::get('dashboard', [SubmissionModerationController::class, 'dashboard'])->name('dashboard');
        Route::get('submissions', [SubmissionModerationController::class, 'index'])->name('submissions.index');
        Route::get('stats', [SubmissionModerationController::class, 'stats'])->name('stats');
        Route::post('submissions/{submission}/approve', [SubmissionModerationController::class, 'approve'])->name('submissions.approve');
        Route::post('submissions/{submission}/reject', [SubmissionModerationController::class, 'reject'])->name('submissions.reject');
    });
});
