<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommendation_interactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('recommended_exercise_id');
            $table->unsignedBigInteger('source_exercise_id')->nullable(); // What exercise they were doing when recommendation was made
            $table->string('recommendation_session_id'); // Track recommendation batches
            $table->integer('recommendation_rank'); // Position in recommendation list (1-10)
            $table->boolean('was_clicked')->default(false);
            $table->boolean('was_completed')->default(false);
            $table->unsignedBigInteger('completion_tracked_in_history_id')->nullable(); // Link to UserExerciseHistory
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'recommendation_session_id'], 'idx_user_session');
            $table->index('was_clicked', 'idx_clicked');
            $table->index('was_completed', 'idx_completed');
            $table->index('created_at', 'idx_created_at');

            // Foreign key constraints (if you have them set up)
            // $table->foreign('user_id')->references('id')->on('users');
            // $table->foreign('completion_tracked_in_history_id')->references('history_id')->on('user_exercise_history');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendation_interactions');
    }
};