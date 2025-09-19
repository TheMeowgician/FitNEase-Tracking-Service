<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class RecommendationInteraction extends Model
{
    use HasFactory;

    protected $table = 'recommendation_interactions';

    protected $fillable = [
        'user_id',
        'recommended_exercise_id',
        'source_exercise_id',
        'recommendation_session_id',
        'recommendation_rank',
        'was_clicked',
        'was_completed',
        'completion_tracked_in_history_id'
    ];

    protected $casts = [
        'was_clicked' => 'boolean',
        'was_completed' => 'boolean',
        'recommendation_rank' => 'integer'
    ];

    // Relationships
    public function userExerciseHistory()
    {
        return $this->belongsTo(UserExerciseHistory::class, 'completion_tracked_in_history_id', 'history_id');
    }

    // Scopes for querying
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForSession($query, $sessionId)
    {
        return $query->where('recommendation_session_id', $sessionId);
    }

    public function scopeClicked($query)
    {
        return $query->where('was_clicked', true);
    }

    public function scopeCompleted($query)
    {
        return $query->where('was_completed', true);
    }

    public function scopeTopRanked($query, $maxRank = 5)
    {
        return $query->where('recommendation_rank', '<=', $maxRank);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // Helper methods
    public function isRelevant()
    {
        return $this->was_clicked || $this->was_completed;
    }

    public function getInteractionTypeAttribute()
    {
        if ($this->was_completed) return 'completed';
        if ($this->was_clicked) return 'clicked';
        return 'shown';
    }

    public function getRankCategoryAttribute()
    {
        if ($this->recommendation_rank <= 3) return 'top';
        if ($this->recommendation_rank <= 5) return 'middle';
        return 'bottom';
    }

    // Static methods for analysis
    public static function getSessionStats($sessionId)
    {
        $interactions = self::where('recommendation_session_id', $sessionId)->get();

        return [
            'total_shown' => $interactions->count(),
            'total_clicked' => $interactions->where('was_clicked', true)->count(),
            'total_completed' => $interactions->where('was_completed', true)->count(),
            'click_through_rate' => $interactions->count() > 0 ?
                ($interactions->where('was_clicked', true)->count() / $interactions->count()) * 100 : 0,
            'completion_rate' => $interactions->count() > 0 ?
                ($interactions->where('was_completed', true)->count() / $interactions->count()) * 100 : 0
        ];
    }

    public static function getUserStats($userId, $days = 30)
    {
        $startDate = Carbon::now()->subDays($days);
        $interactions = self::where('user_id', $userId)
            ->where('created_at', '>=', $startDate)
            ->get();

        $sessions = $interactions->groupBy('recommendation_session_id');

        return [
            'total_sessions' => $sessions->count(),
            'total_recommendations_seen' => $interactions->count(),
            'total_clicked' => $interactions->where('was_clicked', true)->count(),
            'total_completed' => $interactions->where('was_completed', true)->count(),
            'average_ctr' => $sessions->count() > 0 ?
                $sessions->avg(function ($session) {
                    $clicked = $session->where('was_clicked', true)->count();
                    return $session->count() > 0 ? ($clicked / $session->count()) * 100 : 0;
                }) : 0,
        ];
    }
}
