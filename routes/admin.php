<?php

use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::patch('categories/{category}', [CategoryController::class, 'update'])->name('categories.update');

        Route::get('users', [UserRoleController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/role', [UserRoleController::class, 'update'])->name('users.role');

        // Dùng lại QueueController — cùng trang Inertia 'feedback/queue' với
        // prop `scope=admin`, không cần controller riêng (mục 6 tài liệu).
        Route::get('feedback', [QueueController::class, 'index'])->name('feedback.index');
    });
