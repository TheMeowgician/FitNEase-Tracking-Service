<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserExerciseHistory extends Model
{
    use HasFactory;

    protected $table = 'user_exercise_history';
    protected $primaryKey = 'history_id';

    protected $fillable = [
        'user_id',
        'exercise_id',
        'session_id',
        'completed_repetitions',
        'completed_duration_seconds',
        'performance_score',
        'difficulty_perceived',
        'form_rating',
        'fatigue_level',
        'completed_at',
        'came_from_recommendation',
        'recommendation_session_id'
    ];

    protected $casts = [
        'performance_score' => 'decimal:2',
        'form_rating' => 'decimal:2',
        'completed_at' => 'datetime',
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

    public function scopeForExercise($query, $exerciseId)
    {
        return $query->where('exercise_id', $exerciseId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('completed_at', 'desc');
    }

    public function scopeHighPerformance($query, $threshold = 7.0)
    {
        return $query->where('performance_score', '>=', $threshold);
    }

    public function scopeLowPerformance($query, $threshold = 4.0)
    {
        return $query->where('performance_score', '<=', $threshold);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('completed_at', [$startDate, $endDate]);
    }

    public function getFormattedDurationAttribute()
    {
        if (!$this->completed_duration_seconds) return null;

        $minutes = floor($this->completed_duration_seconds / 60);
        $seconds = $this->completed_duration_seconds % 60;

        if ($minutes > 0) {
            return $minutes . 'm ' . $seconds . 's';
        }
        return $seconds . 's';
    }

    public function getPerformanceGradeAttribute()
    {
        if (!$this->performance_score) return null;

        if ($this->performance_score >= 9.0) return 'Excellent';
        if ($this->performance_score >= 7.0) return 'Good';
        if ($this->performance_score >= 5.0) return 'Average';
        if ($this->performance_score >= 3.0) return 'Below Average';
        return 'Poor';
    }

    public function getDifficultyLevelAttribute()
    {
        switch ($this->difficulty_perceived) {
            case 'very_easy':
                return 'Very Easy';
            case 'easy':
                return 'Easy';
            case 'moderate':
                return 'Moderate';
            case 'hard':
                return 'Hard';
            case 'very_hard':
                return 'Very Hard';
            default:
                return 'Not Rated';
        }
    }

    public function getFatigueLevelTextAttribute()
    {
        switch ($this->fatigue_level) {
            case 'none':
                return 'No Fatigue';
            case 'low':
                return 'Low Fatigue';
            case 'moderate':
                return 'Moderate Fatigue';
            case 'high':
                return 'High Fatigue';
            case 'extreme':
                return 'Extreme Fatigue';
            default:
                return 'Not Rated';
        }
    }

    public function getFormRatingGradeAttribute()
    {
        if (!$this->form_rating) return null;

        if ($this->form_rating >= 4.5) return 'Perfect Form';
        if ($this->form_rating >= 3.5) return 'Good Form';
        if ($this->form_rating >= 2.5) return 'Average Form';
        if ($this->form_rating >= 1.5) return 'Poor Form';
        return 'Very Poor Form';
    }

    public static function getDifficultyScale()
    {
        return [
            'very_easy' => 1,
            'easy' => 2,
            'moderate' => 3,
            'hard' => 4,
            'very_hard' => 5
        ];
    }

    public static function getFatigueScale()
    {
        return [
            'none' => 0,
            'low' => 1,
            'moderate' => 2,
            'high' => 3,
            'extreme' => 4
        ];
    }
}