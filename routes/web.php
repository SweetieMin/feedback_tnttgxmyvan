<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserAvatarController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::get('media/avatars/{filename}', UserAvatarController::class)
    ->where('filename', '[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp|gif)')
    ->name('media.avatars.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/feedback.php';
require __DIR__.'/notifications.php';
