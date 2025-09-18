<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WeeklySummary extends Model
{
    use HasFactory;

    protected $table = 'weekly_summaries';
    protected $primaryKey = 'summary_id';

    protected $fillable = [
        'user_id',
        'week_start_date',
        'week_end_date',
        'total_workouts',
        'total_calories_burned',
        'total_exercise_time_minutes',
        'weight_loss_kg',
        'group_activities_count',
        'achievements_earned',
        'average_performance_rating',
        'improvement_percentage',
        'generated_at'
    ];

    protected $casts = [
        'week_start_date' => 'date',
        'week_end_date' => 'date',
        'total_calories_burned' => 'decimal:2',
        'weight_loss_kg' => 'decimal:2',
        'average_performance_rating' => 'decimal:2',
        'improvement_percentage' => 'decimal:2',
        'generated_at' => 'datetime'
    ];

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('week_start_date', 'desc');
    }

    public function scopeForWeek($query, $weekStart)
    {
        return $query->where('week_start_date', $weekStart);
    }

    public function getWeekRangeAttribute()
    {
        return $this->week_start_date->format('M j') . ' - ' . $this->week_end_date->format('M j, Y');
    }

    public function getFormattedCaloriesAttribute()
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

    public function getPerformanceGradeAttribute()
    {
        if (!$this->average_performance_rating) return null;

        if ($this->average_performance_rating >= 4.5) return 'Excellent';
        if ($this->average_performance_rating >= 3.5) return 'Good';
        if ($this->average_performance_rating >= 2.5) return 'Average';
        if ($this->average_performance_rating >= 1.5) return 'Below Average';
        return 'Poor';
    }
}