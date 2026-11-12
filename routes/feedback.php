<?php

use App\Http\Controllers\FeedbackAssignmentController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\FeedbackImageController;
use App\Http\Controllers\FeedbackMessageController;
use App\Http\Controllers\FeedbackStatusController;
use App\Http\Controllers\QueueController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('feedback', [FeedbackController::class, 'index'])->name('feedback.index');
    Route::get('feedback/create', [FeedbackController::class, 'create'])->name('feedback.create');
    Route::post('feedback', [FeedbackController::class, 'store'])->name('feedback.store');
    Route::get('feedback/{feedback}', [FeedbackController::class, 'show'])->name('feedback.show');
    Route::patch('feedback/{feedback}', [FeedbackController::class, 'update'])->name('feedback.update');

    // Mở lại là hành động của chủ góp ý HOẶC handler/admin — Policy tự phân
    // biệt, không gate bằng role middleware.
    Route::post('feedback/{feedback}/reopen', [FeedbackStatusController::class, 'reopen'])->name('feedback.reopen');

    // Trả lời công khai (ai cũng gửi được) / ghi chú nội bộ (chỉ handler,
    // admin) — Policy FeedbackMessagePolicy::create tự phân biệt theo visibility.
    Route::post('feedback/{feedback}/messages', [FeedbackMessageController::class, 'store'])->name('feedback.messages.store');

    Route::post('feedback-images', [FeedbackImageController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('feedback.images.store');
});

Route::middleware(['auth', 'verified', 'role:handler,admin'])->group(function () {
    Route::get('queue', [QueueController::class, 'index'])->name('queue.index');
    Route::post('feedback/{feedback}/self-assign', [FeedbackAssignmentController::class, 'selfAssign'])->name('feedback.self-assign');
    Route::patch('feedback/{feedback}/status', [FeedbackStatusController::class, 'update'])->name('feedback.status.update');
});

Route::middleware(['auth', 'verified', 'role:admin'])->group(function () {
    Route::patch('feedback/{feedback}/assignee', [FeedbackAssignmentController::class, 'reassign'])->name('feedback.reassign');
});
