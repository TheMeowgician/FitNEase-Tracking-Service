<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id('session_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('workout_id');
            $table->unsignedBigInteger('group_id')->nullable();
            $table->enum('session_type', ['individual', 'group']);
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->integer('actual_duration_minutes')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->decimal('completion_percentage', 5, 2)->default(0.00);
            $table->decimal('calories_burned', 6, 2)->nullable();
            $table->decimal('performance_rating', 3, 2)->nullable();
            $table->text('user_notes')->nullable();
            $table->integer('heart_rate_avg')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'created_at'], 'idx_workout_sessions_user_date');
            $table->index(['user_id', 'is_completed', 'created_at'], 'idx_sessions_completion');
            $table->index(['user_id', 'start_time', 'is_completed'], 'idx_sessions_time_patterns');
            $table->index(['user_id', 'workout_id', 'performance_rating'], 'idx_sessions_workout_preferences');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};