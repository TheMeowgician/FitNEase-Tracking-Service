<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Additional performance indexes for behavioral pattern analysis (ML support)
        // These indexes are specifically mentioned in the requirements document

        // User workout history queries - already created in main migration
        // CREATE INDEX idx_workout_sessions_user_date ON WorkoutSessions(user_id, created_at);

        // Progress tracking queries - already created in main migration
        // CREATE INDEX idx_progress_user_date ON ProgressTracking(user_id, tracking_date);

        // BMI history tracking - already created in main migration
        // CREATE INDEX idx_bmi_user_date ON BMIRecords(user_id, recorded_at);

        // Weekly summary lookups - already created in main migration
        // CREATE INDEX idx_weekly_summaries_user_week ON WeeklySummaries(user_id, week_start_date);

        // Session performance analytics - already created in main migration
        // CREATE INDEX idx_sessions_completion ON WorkoutSessions(user_id, is_completed, created_at);

        // Additional behavioral pattern analysis indexes (ML support) - not yet created
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sessions_time_patterns ON workout_sessions(user_id, start_time, is_completed)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sessions_workout_preferences ON workout_sessions(user_id, workout_id, performance_rating)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_workout_ratings_user_workout ON workout_ratings(user_id, workout_id, rating_value)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exercise_history_user_exercise ON user_exercise_history(user_id, exercise_id, performance_score)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_progress_improvement_trends ON progress_tracking(user_id, tracking_date, performance_improvement_percentage)');

        // Additional composite indexes for complex ML queries
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sessions_ml_analysis ON workout_sessions(user_id, created_at, session_type, performance_rating, is_completed)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ratings_ml_analysis ON workout_ratings(user_id, workout_id, rating_value, enjoyment_rating, difficulty_rating)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_exercise_ml_analysis ON user_exercise_history(user_id, exercise_id, performance_score, difficulty_perceived, completed_at)');

        // Indexes for cross-service communication
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sessions_engagement_data ON workout_sessions(user_id, workout_id, is_completed, created_at)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_sessions_planning_data ON workout_sessions(workout_id, user_id, completion_percentage, performance_rating)');
    }

    public function down(): void
    {
        // Drop the additional indexes
        DB::statement('DROP INDEX IF EXISTS idx_sessions_time_patterns');
        DB::statement('DROP INDEX IF EXISTS idx_sessions_workout_preferences');
        DB::statement('DROP INDEX IF EXISTS idx_workout_ratings_user_workout');
        DB::statement('DROP INDEX IF EXISTS idx_exercise_history_user_exercise');
        DB::statement('DROP INDEX IF EXISTS idx_progress_improvement_trends');
        DB::statement('DROP INDEX IF EXISTS idx_sessions_ml_analysis');
        DB::statement('DROP INDEX IF EXISTS idx_ratings_ml_analysis');
        DB::statement('DROP INDEX IF EXISTS idx_exercise_ml_analysis');
        DB::statement('DROP INDEX IF EXISTS idx_sessions_engagement_data');
        DB::statement('DROP INDEX IF EXISTS idx_sessions_planning_data');
    }
};