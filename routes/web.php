<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\JobModerationController;
use App\Http\Controllers\Admin\SubmissionModerationController;
use App\Http\Controllers\Admin\ThreadModerationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ConversationMessageController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\FriendController;
use App\Http\Controllers\IdCheckController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobTrackerController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicMediaController;
use App\Http\Controllers\SavedPostController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\ThreadController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/wall');
Route::post('/submissions', [SubmissionController::class, 'store'])
    ->middleware('throttle:submission')
    ->name('submissions.store');
Route::post('/submissions/{submission}/vote', [SubmissionController::class, 'vote'])
    ->middleware('throttle:submission-vote')
    ->name('submissions.vote');
Route::get('/media/{path}', [PublicMediaController::class, 'show'])
    ->where('path', '.*')
    ->name('media.show');
Route::get('/chat', [ChatController::class, 'index'])->name('chat');
Route::get('/chat/messages', [ChatController::class, 'fetch'])->name('chat.messages.index');
Route::post('/chat/messages', [ChatController::class, 'store'])
    ->middleware('throttle:chat-message')
    ->name('chat.messages.store');
Route::post('/chat/nickname', [ChatController::class, 'updateNickname'])
    ->middleware('throttle:chat-nickname')
    ->name('chat.nickname.update');
Route::get('/wall', [SubmissionController::class, 'wall'])->name('wall');

// Community forum. Public and anonymous like the wall and the chat room, so
// these sit outside the student auth group on purpose.
Route::get('/threads', [ThreadController::class, 'index'])->name('threads.index');
Route::post('/threads', [ThreadController::class, 'store'])
    ->middleware('throttle:thread')
    ->name('threads.store');
Route::get('/threads/{thread}', [ThreadController::class, 'show'])->name('threads.show');
Route::post('/threads/{thread}/replies', [ThreadController::class, 'storeReply'])
    ->middleware('throttle:thread-reply')
    ->name('threads.replies.store');
Route::post('/threads/{thread}/report', [ThreadController::class, 'reportThread'])
    ->middleware('throttle:thread-report')
    ->name('threads.report');
Route::post('/thread-replies/{reply}/vote', [ThreadController::class, 'vote'])
    ->middleware('throttle:thread-vote')
    ->name('threads.replies.vote');
Route::post('/thread-replies/{reply}/report', [ThreadController::class, 'reportReply'])
    ->middleware('throttle:thread-report')
    ->name('threads.replies.report');
// JobRadar. Browsing and sharing a link are public like the rest of the wall;
// saving, tracking and monitors need a verified student and sit further down.
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::post('/jobs', [JobController::class, 'store'])
    ->middleware('throttle:job-share')
    ->name('jobs.store');
Route::get('/jobs/{listing}', [JobController::class, 'show'])->whereNumber('listing')->name('jobs.show');
Route::post('/jobs/{listing}/report', [JobController::class, 'report'])
    ->whereNumber('listing')
    ->middleware('throttle:job-report')
    ->name('jobs.report');
Route::get('/id-check', [IdCheckController::class, 'index'])->name('id-check');
Route::redirect('/id', '/id-check');

// Directory of the apps we've shipped. The list itself lives in
// resources/js/lib/apps.js because the newsfeed sidebar renders it too, so
// there is nothing for a controller to hand over here.
Route::inertia('/tools', 'Tools/Index')->name('tools');
Route::redirect('/apps', '/tools');

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

        Route::post('posts', [PostController::class, 'store'])->name('posts.store');
        Route::delete('posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');
        Route::post('posts/{post}/save', [PostController::class, 'save'])->name('posts.save');
        Route::delete('posts/{post}/save', [PostController::class, 'unsave'])->name('posts.unsave');
        Route::post('posts/{post}/react', [PostController::class, 'react'])->name('posts.react');
        Route::delete('posts/{post}/react', [PostController::class, 'unreact'])->name('posts.unreact');
        Route::post('posts/{post}/comments', [PostController::class, 'storeComment'])->name('posts.comments.store');
        Route::get('saved', [SavedPostController::class, 'index'])->name('saved');

        Route::get('friends', [FriendController::class, 'index'])->name('friends.index');
        Route::post('friends/{user:username}', [FriendController::class, 'store'])->name('friends.store');
        Route::post('friends/{friendship}/accept', [FriendController::class, 'accept'])->name('friends.accept');
        Route::delete('friends/{friendship}', [FriendController::class, 'destroy'])->name('friends.destroy');

        Route::get('journal', [JournalController::class, 'index'])->name('journal.index');
        Route::post('journal', [JournalController::class, 'store'])->name('journal.store');
        Route::patch('journal/{entry}', [JournalController::class, 'update'])->name('journal.update');
        Route::delete('journal/{entry}', [JournalController::class, 'destroy'])->name('journal.destroy');

        Route::get('jobs/mine', [JobTrackerController::class, 'mine'])->name('jobs.mine');
        Route::post('jobs/{listing}/save', [JobTrackerController::class, 'save'])->whereNumber('listing')->name('jobs.save');
        Route::delete('jobs/{listing}/save', [JobTrackerController::class, 'unsave'])->whereNumber('listing')->name('jobs.unsave');
        Route::patch('job-saves/{save}', [JobTrackerController::class, 'updateSave'])->name('jobs.saves.update');
        Route::delete('job-saves/{save}', [JobTrackerController::class, 'destroySave'])->name('jobs.saves.destroy');
        Route::post('job-monitors', [JobTrackerController::class, 'storeMonitor'])
            ->middleware('throttle:job-monitor')
            ->name('jobs.monitors.store');
        Route::patch('job-monitors/{monitor}', [JobTrackerController::class, 'updateMonitor'])
            ->middleware('throttle:job-monitor')
            ->name('jobs.monitors.update');
        Route::delete('job-monitors/{monitor}', [JobTrackerController::class, 'destroyMonitor'])->name('jobs.monitors.destroy');

        Route::get('messages', [ConversationController::class, 'index'])->name('messages.index');
        Route::post('messages', [ConversationController::class, 'store'])->name('conversations.store');
        Route::get('messages/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::delete('messages/{conversation}/leave', [ConversationController::class, 'leave'])->name('conversations.leave');
        Route::get('messages/{conversation}/items', [ConversationMessageController::class, 'fetch'])->name('conversations.messages.fetch');
        Route::post('messages/{conversation}/items', [ConversationMessageController::class, 'store'])
            ->middleware('throttle:direct-message')
            ->name('conversations.messages.store');
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AdminAuthController::class, 'create'])->name('login');
        Route::post('login', [AdminAuthController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [AdminAuthController::class, 'destroy'])->name('logout');

        Route::get('dashboard', [SubmissionModerationController::class, 'dashboard'])->name('dashboard');
        Route::get('submissions', [SubmissionModerationController::class, 'index'])->name('submissions.index');
        Route::get('stats', [SubmissionModerationController::class, 'stats'])->name('stats');
        Route::post('submissions/approve-all', [SubmissionModerationController::class, 'approveAll'])->name('submissions.approveAll');
        Route::post('submissions/{submission}/approve', [SubmissionModerationController::class, 'approve'])->name('submissions.approve');
        Route::post('submissions/{submission}/reject', [SubmissionModerationController::class, 'reject'])->name('submissions.reject');

        Route::get('jobs', [JobModerationController::class, 'page'])->name('jobs');
        Route::get('jobs/listings', [JobModerationController::class, 'listings'])->name('jobs.listings');
        Route::post('jobs/listings/{listing}/approve', [JobModerationController::class, 'approve'])->name('jobs.listings.approve');
        Route::post('jobs/listings/{listing}/reject', [JobModerationController::class, 'reject'])->name('jobs.listings.reject');
        Route::post('jobs/listings/{listing}/close', [JobModerationController::class, 'close'])->name('jobs.listings.close');
        Route::get('jobs/sources', [JobModerationController::class, 'sources'])->name('jobs.sources');
        Route::post('jobs/sources', [JobModerationController::class, 'storeSource'])->name('jobs.sources.store');
        Route::patch('jobs/sources/{source}', [JobModerationController::class, 'updateSource'])->name('jobs.sources.update');
        Route::delete('jobs/sources/{source}', [JobModerationController::class, 'destroySource'])->name('jobs.sources.destroy');
        Route::post('jobs/sources/{source}/fetch', [JobModerationController::class, 'fetchSource'])->name('jobs.sources.fetch');

        Route::get('thread-reports', [ThreadModerationController::class, 'reports'])->name('threads.reports');
        Route::post('thread-reports/{report}/resolve', [ThreadModerationController::class, 'resolve'])->name('threads.reports.resolve');
        Route::post('threads/{thread}/hide', [ThreadModerationController::class, 'hideThread'])->name('threads.hide');
        Route::post('threads/{thread}/restore', [ThreadModerationController::class, 'restoreThread'])->name('threads.restore');
        Route::post('thread-replies/{reply}/hide', [ThreadModerationController::class, 'hideReply'])->name('threads.replies.hide');
        Route::post('thread-replies/{reply}/restore', [ThreadModerationController::class, 'restoreReply'])->name('threads.replies.restore');
    });
});
