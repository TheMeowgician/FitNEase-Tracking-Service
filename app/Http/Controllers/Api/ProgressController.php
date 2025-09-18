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
                    'improvement_percentage' => 0, // Would need previous week data
                    'achievements_earned' => 0 // Would integrate with engagement service
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
}