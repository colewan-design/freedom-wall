<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
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
        Route::get('submissions/failed-fb', [SubmissionModerationController::class, 'failedFacebook'])->name('submissions.failed-fb');
        Route::get('stats', [SubmissionModerationController::class, 'stats'])->name('stats');
        Route::post('submissions/{submission}/approve', [SubmissionModerationController::class, 'approve'])->name('submissions.approve');
        Route::post('submissions/{submission}/reject', [SubmissionModerationController::class, 'reject'])->name('submissions.reject');
        Route::post('submissions/{submission}/retry-fb', [SubmissionModerationController::class, 'retryFacebook'])->name('submissions.retry-fb');
    });
});
