<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_summaries', function (Blueprint $table) {
            $table->id('summary_id');
            $table->unsignedBigInteger('user_id');
            $table->date('week_start_date');
            $table->date('week_end_date');
            $table->integer('total_workouts')->default(0);
            $table->decimal('total_calories_burned', 8, 2)->default(0.00);
            $table->integer('total_exercise_time_minutes')->default(0);
            $table->decimal('weight_loss_kg', 5, 2)->default(0.00);
            $table->integer('group_activities_count')->default(0);
            $table->integer('achievements_earned')->default(0);
            $table->decimal('average_performance_rating', 3, 2)->nullable();
            $table->decimal('improvement_percentage', 5, 2)->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->timestamps();

            // Unique index
            $table->unique(['user_id', 'week_start_date']);

            // Additional indexes
            $table->index(['user_id', 'week_start_date'], 'idx_weekly_summaries_user_week');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_summaries');
    }
};