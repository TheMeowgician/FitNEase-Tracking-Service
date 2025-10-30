<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * WorkoutExerciseRating Model
 *
 * Stores individual exercise ratings (not workout ratings).
 * This is the KEY data source for collaborative filtering in the ML service.
 *
 * Unlike WorkoutRating which rates entire workouts, this model rates
 * individual exercises within a workout session, enabling personalized
 * exercise recommendations based on user preferences.
 */
class WorkoutExerciseRating extends Model
{
    use HasFactory;

    protected $table = 'workout_exercise_ratings';
    protected $primaryKey = 'rating_id';

    protected $fillable = [
        'user_id',
        'exercise_id',
        'workout_id',
        'session_id',
        'rating_value',
        'difficulty_perceived',
        'enjoyment_rating',
        'would_do_again',
        'notes',
        'completed',
        'completed_reps',
        'completed_duration_seconds',
        'came_from_recommendation',
        'recommendation_session_id',
        'rated_at',
    ];

    protected $casts = [
        'rating_value' => 'decimal:2',
        'enjoyment_rating' => 'decimal:2',
        'would_do_again' => 'boolean',
        'completed' => 'boolean',
        'came_from_recommendation' => 'boolean',
        'rated_at' => 'datetime',
    ];

    /**
     * Relationships
     */

    public function workoutSession()
    {
        return $this->belongsTo(WorkoutSession::class, 'session_id', 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Query Scopes
     */

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForExercise($query, $exerciseId)
    {
        return $query->where('exercise_id', $exerciseId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    public function scopeHighRated($query, $threshold = 4.0)
    {
        return $query->where('rating_value', '>=', $threshold);
    }

    public function scopeLowRated($query, $threshold = 2.5)
    {
        return $query->where('rating_value', '<=', $threshold);
    }

    public function scopeFromRecommendations($query)
    {
        return $query->where('came_from_recommendation', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeIncomplete($query)
    {
        return $query->where('completed', false);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('rated_at', 'desc');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('rated_at', [$startDate, $endDate]);
    }

    /**
     * Accessors - Human-readable attributes
     */

    public function getRatingGradeAttribute()
    {
        if ($this->rating_value >= 4.5) return 'Excellent';
        if ($this->rating_value >= 3.5) return 'Good';
        if ($this->rating_value >= 2.5) return 'Average';
        if ($this->rating_value >= 1.5) return 'Below Average';
        return 'Poor';
    }

    public function getEnjoymentGradeAttribute()
    {
        if (!$this->enjoyment_rating) return null;

        if ($this->enjoyment_rating >= 4.5) return 'Loved It';
        if ($this->enjoyment_rating >= 3.5) return 'Enjoyed';
        if ($this->enjoyment_rating >= 2.5) return 'Neutral';
        if ($this->enjoyment_rating >= 1.5) return 'Disliked';
        return 'Hated It';
    }

    public function getDifficultyFeedbackAttribute()
    {
        switch ($this->difficulty_perceived) {
            case 'too_easy':
                return 'Too Easy';
            case 'appropriate':
                return 'Just Right';
            case 'challenging':
                return 'Challenging';
            case 'too_hard':
                return 'Too Hard';
            default:
                return 'Not Rated';
        }
    }

    public function getStarRatingAttribute()
    {
        return str_repeat('⭐', (int) round($this->rating_value));
    }

    /**
     * Helper Methods
     */

    /**
     * Get all ratings for ML collaborative filtering
     * Returns in format: [user_id, exercise_id, rating_value]
     *
     * @return array
     */
    public static function getMLFormattedRatings()
    {
        return static::query()
            ->select('user_id', 'exercise_id', 'rating_value', 'rated_at')
            ->completed() // Only include completed exercises
            ->orderBy('rated_at', 'desc')
            ->get()
            ->map(function ($rating) {
                return [
                    'user_id' => $rating->user_id,
                    'exercise_id' => $rating->exercise_id,
                    'rating' => (float) $rating->rating_value,
                    'rated_at' => $rating->rated_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get user's rating history for a specific exercise
     *
     * @param int $userId
     * @param int $exerciseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getUserExerciseHistory($userId, $exerciseId)
    {
        return static::forUser($userId)
            ->forExercise($exerciseId)
            ->latestFirst()
            ->get();
    }

    /**
     * Calculate average rating for an exercise
     *
     * @param int $exerciseId
     * @return float|null
     */
    public static function getExerciseAverageRating($exerciseId)
    {
        return static::forExercise($exerciseId)
            ->completed()
            ->avg('rating_value');
    }

    /**
     * Get recommendation accuracy metrics
     * Compares ratings of recommended vs non-recommended exercises
     *
     * @param int $userId
     * @return array
     */
    public static function getRecommendationAccuracy($userId)
    {
        $recommendedRatings = static::forUser($userId)
            ->fromRecommendations()
            ->completed()
            ->avg('rating_value');

        $organicRatings = static::forUser($userId)
            ->where('came_from_recommendation', false)
            ->completed()
            ->avg('rating_value');

        return [
            'recommended_avg' => round($recommendedRatings ?? 0, 2),
            'organic_avg' => round($organicRatings ?? 0, 2),
            'recommendation_lift' => round(($recommendedRatings - $organicRatings) ?? 0, 2),
        ];
    }
}
