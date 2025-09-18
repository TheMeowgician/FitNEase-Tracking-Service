<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ProgressTracking extends Model
{
    use HasFactory;

    protected $table = 'progress_tracking';
    protected $primaryKey = 'progress_id';

    protected $fillable = [
        'user_id',
        'tracking_date',
        'total_workouts_completed',
        'total_calories_burned',
        'total_exercise_time_minutes',
        'weight_change_kg',
        'performance_improvement_percentage',
        'streak_days',
        'longest_streak_days'
    ];

    protected $casts = [
        'tracking_date' => 'date',
        'total_calories_burned' => 'decimal:2',
        'weight_change_kg' => 'decimal:2',
        'performance_improvement_percentage' => 'decimal:2'
    ];

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('tracking_date', [$startDate, $endDate]);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('tracking_date', 'desc');
    }

    public function getFormattedCaloriesBurnedAttribute()
    {
        return number_format($this->total_calories_burned, 0) . ' cal';
    }

    public function getFormattedExerciseTimeAttribute()
    {
        $hours = floor($this->total_exercise_time_minutes / 60);
        $minutes = $this->total_exercise_time_minutes % 60;

        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'm';
        }
        return $minutes . 'm';
    }
}