<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('progress_tracking', function (Blueprint $table) {
            $table->id('progress_id');
            $table->unsignedBigInteger('user_id');
            $table->date('tracking_date');
            $table->integer('total_workouts_completed')->default(0);
            $table->decimal('total_calories_burned', 8, 2)->default(0.00);
            $table->integer('total_exercise_time_minutes')->default(0);
            $table->decimal('weight_change_kg', 5, 2)->default(0.00);
            $table->decimal('performance_improvement_percentage', 5, 2)->default(0.00);
            $table->integer('streak_days')->default(0);
            $table->integer('longest_streak_days')->default(0);
            $table->timestamps();

            // Unique index
            $table->unique(['user_id', 'tracking_date']);

            // Additional indexes
            $table->index(['user_id', 'tracking_date'], 'idx_progress_user_date');
            $table->index(['user_id', 'tracking_date', 'performance_improvement_percentage'], 'idx_progress_improvement_trends');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_tracking');
    }
};