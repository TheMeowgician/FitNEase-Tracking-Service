<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WorkoutRating extends Model
{
    use HasFactory;

    protected $table = 'workout_ratings';
    protected $primaryKey = 'rating_id';

    protected $fillable = [
        'user_id',
        'workout_id',
        'session_id',
        'rating_value',
        'difficulty_rating',
        'enjoyment_rating',
        'would_recommend',
        'feedback_comment',
        'rated_at',
        'came_from_recommendation',
        'recommendation_session_id'
    ];

    protected $casts = [
        'rating_value' => 'decimal:2',
        'enjoyment_rating' => 'decimal:2',
        'would_recommend' => 'boolean',
        'rated_at' => 'datetime',
        'came_from_recommendation' => 'boolean'
    ];

    public function workoutSession()
    {
        return $this->belongsTo(WorkoutSession::class, 'session_id', 'session_id');
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForWorkout($query, $workoutId)
    {
        return $query->where('workout_id', $workoutId);
    }

    public function scopeHighRated($query, $threshold = 4.0)
    {
        return $query->where('rating_value', '>=', $threshold);
    }

    public function scopeLowRated($query, $threshold = 2.0)
    {
        return $query->where('rating_value', '<=', $threshold);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('rated_at', 'desc');
    }

    public function scopeRecommended($query)
    {
        return $query->where('would_recommend', true);
    }

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
        switch ($this->difficulty_rating) {
            case 'too_easy':
                return 'Too Easy';
            case 'just_right':
                return 'Perfect Difficulty';
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
}