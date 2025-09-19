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
        // Add columns to user_exercise_history
        Schema::table('user_exercise_history', function (Blueprint $table) {
            $table->boolean('came_from_recommendation')->default(false)->after('completed_at');
            $table->string('recommendation_session_id')->nullable()->after('came_from_recommendation');

            // Add index for recommendation queries
            $table->index(['came_from_recommendation', 'recommendation_session_id'], 'idx_recommendation_tracking');
        });

        // Add columns to workout_ratings
        Schema::table('workout_ratings', function (Blueprint $table) {
            $table->boolean('came_from_recommendation')->default(false)->after('rated_at');
            $table->string('recommendation_session_id')->nullable()->after('came_from_recommendation');

            // Add index for recommendation queries
            $table->index(['came_from_recommendation', 'recommendation_session_id'], 'idx_rating_recommendation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_exercise_history', function (Blueprint $table) {
            $table->dropIndex('idx_recommendation_tracking');
            $table->dropColumn(['came_from_recommendation', 'recommendation_session_id']);
        });

        Schema::table('workout_ratings', function (Blueprint $table) {
            $table->dropIndex('idx_rating_recommendation');
            $table->dropColumn(['came_from_recommendation', 'recommendation_session_id']);
        });
    }
};
