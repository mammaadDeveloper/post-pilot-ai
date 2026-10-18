<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ScheduleController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('posts/create', [PostController::class, 'create'])->name('posts.create');
    Route::get('posts/drafts', [PostController::class, 'drafts'])->name('posts.drafts');
    Route::get('schedule', [ScheduleController::class, 'index'])->name('schedule.index');
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('profile', [ProfileController::class, 'show'])->name('profile.show');
});

require __DIR__.'/settings.php';
