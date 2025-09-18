<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WorkoutSession extends Model
{
    use HasFactory;

    protected $table = 'workout_sessions';
    protected $primaryKey = 'session_id';

    protected $fillable = [
        'user_id',
        'workout_id',
        'group_id',
        'session_type',
        'start_time',
        'end_time',
        'actual_duration_minutes',
        'is_completed',
        'completion_percentage',
        'calories_burned',
        'performance_rating',
        'user_notes',
        'heart_rate_avg'
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_completed' => 'boolean',
        'completion_percentage' => 'decimal:2',
        'calories_burned' => 'decimal:2',
        'performance_rating' => 'decimal:2'
    ];

    public function workoutRatings()
    {
        return $this->hasMany(WorkoutRating::class, 'session_id', 'session_id');
    }

    public function userExerciseHistory()
    {
        return $this->hasMany(UserExerciseHistory::class, 'session_id', 'session_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeGroupSessions($query)
    {
        return $query->where('session_type', 'group');
    }

    public function scopeIndividualSessions($query)
    {
        return $query->where('session_type', 'individual');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function getDurationInMinutesAttribute()
    {
        if ($this->start_time && $this->end_time) {
            return $this->start_time->diffInMinutes($this->end_time);
        }
        return $this->actual_duration_minutes;
    }

    public function getPerformanceGradeAttribute()
    {
        if (!$this->performance_rating) return null;

        if ($this->performance_rating >= 4.5) return 'Excellent';
        if ($this->performance_rating >= 3.5) return 'Good';
        if ($this->performance_rating >= 2.5) return 'Average';
        if ($this->performance_rating >= 1.5) return 'Below Average';
        return 'Poor';
    }
}