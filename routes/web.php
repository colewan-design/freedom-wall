<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\Admin\SubmissionModerationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/wall');
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

Route::middleware('guest')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('register', [RegisteredUserController::class, 'store'])
        ->middleware('throttle:register')
        ->name('register.store');
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'student'])->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('verify-email', EmailVerificationPromptController::class)->name('verification.notice');
    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::middleware('verified')->group(function () {
        Route::get('feed', [FeedController::class, 'index'])->name('feed');
        Route::get('profile/{user:username}', [ProfileController::class, 'show'])->name('profile.show');
        Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    });
});

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
