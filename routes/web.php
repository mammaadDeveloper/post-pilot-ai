<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PostContentController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicationMediaController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SocialAccountController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::get('posts/drafts', [PostController::class, 'drafts'])->name('posts.drafts');
    Route::post('posts/ai/generate', [PostContentController::class, 'generate'])
        ->middleware('throttle:ai-generation')
        ->name('posts.ai.generate');
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::post('schedule', [ScheduleController::class, 'store'])->name('schedule.store');
    Route::delete('schedule/{scheduledPost}', [ScheduleController::class, 'cancel'])->name('schedule.cancel');
    Route::post('schedule/targets/{target}/retry', [ScheduleController::class, 'retry'])->name('schedule.targets.retry');
    Route::get('social-accounts/{platform}/connect', [SocialAccountController::class, 'connect'])->name('social-accounts.connect');
    Route::get('social-accounts/{platform}/callback', [SocialAccountController::class, 'callback'])->name('social-accounts.callback');
    Route::delete('social-accounts/{socialAccount}', [SocialAccountController::class, 'disconnect'])->name('social-accounts.disconnect');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
});

Route::get('publication-media/{target}', [PublicationMediaController::class, 'show'])
    ->middleware('signed')
    ->name('publication-media.show');

require __DIR__.'/settings.php';
