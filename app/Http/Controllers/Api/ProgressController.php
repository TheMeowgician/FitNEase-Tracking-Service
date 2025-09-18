<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProgressTracking;
use App\Models\WeeklySummary;
use App\Models\UserExerciseHistory;
use App\Models\WorkoutSession;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ProgressController extends Controller
{
    public function getUserHistory($userId): JsonResponse
    {
        try {
            $history = UserExerciseHistory::forUser($userId)
                ->with('workoutSession')
                ->latestFirst()
                ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'User exercise history retrieved successfully',
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getProgress($userId): JsonResponse
    {
        try {
            $progress = ProgressTracking::forUser($userId)
                ->latestFirst()
                ->paginate(30);

            return response()->json([
                'success' => true,
                'message' => 'Progress analytics retrieved successfully',
                'data' => $progress
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve progress analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getWeeklySummary($userId): JsonResponse
    {
        try {
            $summary = WeeklySummary::forUser($userId)
                ->latestFirst()
                ->paginate(12);

            return response()->json([
                'success' => true,
                'message' => 'Weekly summary retrieved successfully',
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve weekly summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function generateWeeklySummary(Request $request, $userId): JsonResponse
    {
        try {
            $weekStart = $request->input('week_start', Carbon::now()->startOfWeek()->toDateString());
            $weekEnd = Carbon::parse($weekStart)->addDays(6);

            $sessions = WorkoutSession::forUser($userId)
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->get();

            $completedSessions = $sessions->where('is_completed', true);
            $groupSessions = $sessions->where('session_type', 'group');

            $summary = WeeklySummary::updateOrCreate(
                ['user_id' => $userId, 'week_start_date' => $weekStart],
                [
                    'week_end_date' => $weekEnd,
                    'total_workouts' => $completedSessions->count(),
                    'total_calories_burned' => $completedSessions->sum('calories_burned'),
                    'total_exercise_time_minutes' => $completedSessions->sum('actual_duration_minutes'),
                    'group_activities_count' => $groupSessions->where('is_completed', true)->count(),
                    'average_performance_rating' => $completedSessions->avg('performance_rating'),
                    'improvement_percentage' => $this->calculateImprovementPercentage($userId, $weekStart, $weekEnd),
                    'achievements_earned' => $this->countWeeklyAchievements($userId, $weekStart, $weekEnd)
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Weekly summary generated successfully',
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate weekly summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function calculateImprovementPercentage($userId, $weekStart, $weekEnd)
    {
        try {
            $currentWeekStart = Carbon::parse($weekStart);
            $previousWeekStart = $currentWeekStart->copy()->subWeek();
            $previousWeekEnd = $previousWeekStart->copy()->addDays(6);

            // Get current week performance
            $currentWeekSessions = WorkoutSession::forUser($userId)
                ->completed()
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->get();

            // Get previous week performance
            $previousWeekSessions = WorkoutSession::forUser($userId)
                ->completed()
                ->whereBetween('created_at', [$previousWeekStart, $previousWeekEnd])
                ->get();

            if ($previousWeekSessions->isEmpty()) {
                return 0; // No previous data to compare
            }

            $currentAvgPerformance = $currentWeekSessions->avg('performance_rating') ?: 0;
            $previousAvgPerformance = $previousWeekSessions->avg('performance_rating') ?: 0;

            if ($previousAvgPerformance == 0) {
                return 0;
            }

            $improvement = (($currentAvgPerformance - $previousAvgPerformance) / $previousAvgPerformance) * 100;
            return round($improvement, 2);

        } catch (\Exception $e) {
            return 0;
        }
    }

    private function countWeeklyAchievements($userId, $weekStart, $weekEnd)
    {
        // This would integrate with the engagement service
        // For now, we'll calculate basic achievements based on tracking data
        try {
            $achievements = 0;

            $sessions = WorkoutSession::forUser($userId)
                ->completed()
                ->whereBetween('created_at', [$weekStart, $weekEnd])
                ->get();

            // Achievement: Completed 3+ workouts this week
            if ($sessions->count() >= 3) {
                $achievements++;
            }

            // Achievement: Consistent performance (avg rating >= 4.0)
            if ($sessions->avg('performance_rating') >= 4.0) {
                $achievements++;
            }

            // Achievement: Burned 1000+ calories this week
            if ($sessions->sum('calories_burned') >= 1000) {
                $achievements++;
            }

            // Achievement: Completed both individual and group sessions
            $hasIndividual = $sessions->where('session_type', 'individual')->count() > 0;
            $hasGroup = $sessions->where('session_type', 'group')->count() > 0;
            if ($hasIndividual && $hasGroup) {
                $achievements++;
            }

            return $achievements;

        } catch (\Exception $e) {
            return 0;
        }
    }
}