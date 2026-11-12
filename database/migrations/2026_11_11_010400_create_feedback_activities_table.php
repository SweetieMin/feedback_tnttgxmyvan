<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('feedback_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_id')->constrained('feedbacks')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['status_change', 'assignment_change', 'reopened']);
            $table->string('from_value', 50)->nullable();
            $table->string('to_value', 50)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['feedback_id', 'created_at']);
            $table->index(['type', 'to_value']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedback_activities');
    }
};
