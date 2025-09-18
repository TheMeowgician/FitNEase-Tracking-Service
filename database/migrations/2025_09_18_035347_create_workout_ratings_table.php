<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_ratings', function (Blueprint $table) {
            $table->id('rating_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workout_id');
            $table->unsignedBigInteger('session_id');
            $table->decimal('rating_value', 3, 2);
            $table->enum('difficulty_rating', ['too_easy', 'just_right', 'too_hard'])->nullable();
            $table->decimal('enjoyment_rating', 3, 2)->nullable();
            $table->boolean('would_recommend')->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('rated_at')->useCurrent();
            $table->timestamps();

            // Unique index
            $table->unique(['user_id', 'workout_id', 'session_id']);

            // Additional indexes
            $table->index(['user_id', 'workout_id', 'rating_value'], 'idx_workout_ratings_user_workout');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_ratings');
    }
};