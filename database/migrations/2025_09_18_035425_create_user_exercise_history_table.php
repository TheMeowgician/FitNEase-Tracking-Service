<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_exercise_history', function (Blueprint $table) {
            $table->id('history_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('exercise_id');
            $table->unsignedBigInteger('session_id');
            $table->integer('completed_repetitions')->nullable();
            $table->integer('completed_duration_seconds')->nullable();
            $table->decimal('performance_score', 5, 2)->nullable();
            $table->enum('difficulty_perceived', ['very_easy', 'easy', 'moderate', 'hard', 'very_hard'])->nullable();
            $table->decimal('form_rating', 3, 2)->nullable();
            $table->enum('fatigue_level', ['none', 'low', 'moderate', 'high', 'extreme'])->nullable();
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'exercise_id', 'completed_at'], 'idx_user_exercise_history_user_exercise');
            $table->index(['user_id', 'exercise_id', 'performance_score'], 'idx_exercise_history_user_exercise');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_exercise_history');
    }
};