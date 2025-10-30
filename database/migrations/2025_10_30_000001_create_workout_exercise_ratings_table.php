<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This table stores individual exercise ratings for collaborative filtering.
     * Unlike workout_ratings (which rates entire workouts), this table rates
     * INDIVIDUAL exercises within a workout session.
     *
     * This is CRITICAL for ML collaborative filtering to work properly.
     */
    public function up(): void
    {
        Schema::create('workout_exercise_ratings', function (Blueprint $table) {
            $table->id('rating_id');

            // Foreign keys
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedBigInteger('workout_id')->nullable(); // Which workout this came from
            $table->unsignedBigInteger('session_id'); // Link to workout session

            // Core rating (REQUIRED - this is what collaborative filtering uses)
            $table->decimal('rating_value', 3, 2); // 1.00 to 5.00 stars

            // Additional feedback (optional but valuable)
            $table->enum('difficulty_perceived', [
                'too_easy',
                'appropriate',
                'challenging',
                'too_hard'
            ])->nullable();

            $table->decimal('enjoyment_rating', 3, 2)->nullable(); // How much user enjoyed it (1-5)
            $table->boolean('would_do_again')->default(true);
            $table->text('notes')->nullable(); // Optional user notes

            // Exercise completion data (for context)
            $table->boolean('completed')->default(true); // Did they complete it?
            $table->integer('completed_reps')->nullable();
            $table->integer('completed_duration_seconds')->nullable();

            // Tracking data
            $table->boolean('came_from_recommendation')->default(false);
            $table->string('recommendation_session_id')->nullable();
            $table->timestamp('rated_at')->useCurrent();

            $table->timestamps();

            // Indexes for performance
            // ML service will query by user_id to get all ratings for collaborative filtering
            $table->index(['user_id', 'rated_at'], 'idx_user_ratings');

            // ML service needs to find ratings for specific exercises
            $table->index(['exercise_id', 'rating_value'], 'idx_exercise_ratings');

            // Query ratings for a specific session
            $table->index('session_id', 'idx_session_ratings');

            // Composite index for ML queries: user-exercise pairs
            $table->index(['user_id', 'exercise_id', 'rating_value'], 'idx_user_exercise_rating');

            // Track recommendation performance
            $table->index(['came_from_recommendation', 'rating_value'], 'idx_recommendation_ratings');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workout_exercise_ratings');
    }
};
