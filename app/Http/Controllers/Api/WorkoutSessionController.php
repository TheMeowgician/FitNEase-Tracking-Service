<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use App\Services\ContentService;
use App\Services\EngagementService;
use App\Services\MLService;
use App\Services\ProgressionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class WorkoutSessionController extends Controller
{
    protected ContentService $contentService;
    protected EngagementService $engagementService;
    protected MLService $mlService;
    protected ProgressionService $progressionService;

    public function __construct(
        ContentService $contentService,
        EngagementService $engagementService,
        MLService $mlService,
        ProgressionService $progressionService
    ) {
        $this->contentService = $contentService;
        $this->engagementService = $engagementService;
        $this->mlService = $mlService;
        $this->progressionService = $progressionService;
    }
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'workout_id' => 'required|integer',
                'group_id' => 'nullable|integer',
                'session_type' => 'required|in:individual,group',
                'start_time' => 'nullable|date',
                'end_time' => 'nullable|date|after:start_time',
                'actual_duration_minutes' => 'nullable|integer|min:0',
                'is_completed' => 'boolean',
                'completion_percentage' => 'numeric|between:0,100',
                'calories_burned' => 'nullable|numeric|min:0',
                'performance_rating' => 'nullable|numeric|between:1,5',
                'user_notes' => 'nullable|string|max:1000',
                'heart_rate_avg' => 'nullable|integer|min:50|max:250'
            ]);

            $session = WorkoutSession::create($validated);

            $token = $request->bearerToken();
            $user = $request->attributes->get('user');

            if ($session->is_completed && $token) {
                $this->notifyServicesOfCompletion($session, $token, $user);
            }

            return response()->json([
                'success' => true,
                'message' => 'Workout session recorded successfully',
                'data' => $session
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record workout session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $sessionId): JsonResponse
    {
        try {
            $session = WorkoutSession::findOrFail($sessionId);

            $validated = $request->validate([
                'end_time' => 'nullable|date',
                'actual_duration_minutes' => 'nullable|integer|min:0',
                'is_completed' => 'boolean',
                'completion_percentage' => 'numeric|between:0,100',
                'calories_burned' => 'nullable|numeric|min:0',
                'performance_rating' => 'nullable|numeric|between:1,5',
                'user_notes' => 'nullable|string|max:1000',
                'heart_rate_avg' => 'nullable|integer|min:50|max:250'
            ]);

            $session->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Workout session updated successfully',
                'data' => $session->fresh()
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update workout session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserSessions(Request $request, $userId): JsonResponse
    {
        try {
            // Allow custom per_page parameter, default to 20, max 1000
            $perPage = min((int) $request->query('per_page', 20), 1000);

            $sessions = WorkoutSession::forUser($userId)
                ->with(['workoutRatings', 'userExerciseHistory'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'User workout sessions retrieved successfully',
                'data' => $sessions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workout sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($sessionId): JsonResponse
    {
        try {
            $session = WorkoutSession::findOrFail($sessionId);
            $session->delete();

            return response()->json([
                'success' => true,
                'message' => 'Workout session deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete workout session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSessionStats($userId): JsonResponse
    {
        try {
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();
            $monthStart = Carbon::now()->startOfMonth();
            $monthEnd = Carbon::now()->endOfMonth();

            // Single query instead of 9 separate queries
            $result = WorkoutSession::forUser($userId)
                ->selectRaw("
                    COUNT(*) as total_sessions,
                    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_sessions,
                    SUM(CASE WHEN is_completed = 1 THEN COALESCE(calories_burned, 0) ELSE 0 END) as total_calories_burned,
                    SUM(CASE WHEN is_completed = 1 THEN COALESCE(actual_duration_minutes, 0) ELSE 0 END) as total_exercise_time,
                    AVG(CASE WHEN is_completed = 1 THEN performance_rating ELSE NULL END) as average_performance_rating,
                    SUM(CASE WHEN is_completed = 1 AND session_type = 'group' THEN 1 ELSE 0 END) as group_sessions_count,
                    SUM(CASE WHEN is_completed = 1 AND session_type = 'individual' THEN 1 ELSE 0 END) as individual_sessions_count,
                    SUM(CASE WHEN is_completed = 1 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_week_sessions,
                    SUM(CASE WHEN is_completed = 1 AND created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_month_sessions
                ", [$weekStart, $weekEnd, $monthStart, $monthEnd])
                ->first();

            $stats = [
                'total_sessions' => (int) $result->total_sessions,
                'completed_sessions' => (int) $result->completed_sessions,
                'total_calories_burned' => (int) $result->total_calories_burned,
                'total_exercise_time' => (int) $result->total_exercise_time,
                'average_performance_rating' => $result->average_performance_rating ? round((float) $result->average_performance_rating, 2) : null,
                'group_sessions_count' => (int) $result->group_sessions_count,
                'individual_sessions_count' => (int) $result->individual_sessions_count,
                'this_week_sessions' => (int) $result->this_week_sessions,
                'this_month_sessions' => (int) $result->this_month_sessions,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Session statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get workout statistics for a specific group
     */
    public function getGroupStats($groupId): JsonResponse
    {
        try {
            $groupIdInt = (int) $groupId;
            $fourWeeksAgo = Carbon::now()->subWeeks(4);
            $weekStart = Carbon::now()->startOfWeek();
            $weekEnd = Carbon::now()->endOfWeek();

            // Single query instead of 6 separate queries
            $result = WorkoutSession::where('group_id', $groupIdInt)
                ->where('is_completed', true)
                ->selectRaw("
                    COUNT(*) as total_workouts,
                    COALESCE(SUM(actual_duration_minutes), 0) as total_minutes,
                    COALESCE(SUM(calories_burned), 0) as total_calories,
                    SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent_sessions,
                    SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as this_week_sessions,
                    COUNT(DISTINCT user_id) as unique_participants
                ", [$fourWeeksAgo, $weekStart, $weekEnd])
                ->first();

            $stats = [
                'total_workouts' => (int) $result->total_workouts,
                'total_minutes' => (int) $result->total_minutes,
                'total_calories' => (int) $result->total_calories,
                'weekly_average' => round((int) $result->recent_sessions / 4, 1),
                'this_week_sessions' => (int) $result->this_week_sessions,
                'unique_participants' => (int) $result->unique_participants,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Group statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve group statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getSessionsByDateRange(Request $request, $userId): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);

            $sessions = WorkoutSession::forUser($userId)
                ->inDateRange($validated['start_date'], $validated['end_date'])
                ->with(['workoutRatings', 'userExerciseHistory'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Sessions for date range retrieved successfully',
                'data' => $sessions
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function checkOvertrainingRisk($userId): JsonResponse
    {
        try {
            $recentSessions = WorkoutSession::forUser($userId)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->get();

            $sessionCount = $recentSessions->count();
            $avgRating = $recentSessions->avg('performance_rating');
            $completionRate = $sessionCount > 0 ? $recentSessions->where('is_completed', true)->count() / $sessionCount : 0;

            $riskFactors = [];

            if ($sessionCount > 6) {
                $riskFactors[] = 'high_frequency';
            }

            if ($avgRating && $avgRating < 3.0) {
                $riskFactors[] = 'declining_performance';
            }

            if ($completionRate < 0.7) {
                $riskFactors[] = 'low_completion';
            }

            $riskLevel = count($riskFactors) > 1 ? 'high' : (count($riskFactors) > 0 ? 'moderate' : 'low');

            $recommendations = $this->getRecoveryRecommendations($riskFactors);

            return response()->json([
                'success' => true,
                'message' => 'Overtraining risk assessment completed',
                'data' => [
                    'risk_level' => $riskLevel,
                    'risk_factors' => $riskFactors,
                    'recommendations' => $recommendations,
                    'session_count_last_7_days' => $sessionCount,
                    'average_performance_rating' => round($avgRating ?: 0, 2),
                    'completion_rate' => round($completionRate * 100, 1)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assess overtraining risk',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getRecoveryRecommendations(array $riskFactors): array
    {
        $recommendations = [];

        if (in_array('high_frequency', $riskFactors)) {
            $recommendations[] = 'Consider reducing workout frequency to allow proper recovery';
            $recommendations[] = 'Schedule at least 1-2 rest days per week';
        }

        if (in_array('declining_performance', $riskFactors)) {
            $recommendations[] = 'Focus on recovery activities like stretching and light cardio';
            $recommendations[] = 'Ensure adequate sleep (7-9 hours per night)';
        }

        if (in_array('low_completion', $riskFactors)) {
            $recommendations[] = 'Reduce workout intensity or duration temporarily';
            $recommendations[] = 'Consider modifying workout plans to match current energy levels';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Great job! Continue your current workout routine';
            $recommendations[] = 'Maintain consistent sleep and nutrition habits';
        }

        return $recommendations;
    }

    private function notifyServicesOfCompletion($session, $token, $user)
    {
        $workoutCompletionData = [
            'user_id' => $session->user_id,
            'workout_id' => $session->workout_id,
            'session_id' => $session->id,
            'completion_percentage' => $session->completion_percentage,
            'performance_rating' => $session->performance_rating,
            'calories_burned' => $session->calories_burned,
            'duration_minutes' => $session->actual_duration_minutes,
            'completed_at' => $session->updated_at->toISOString()
        ];

        // Non-critical notifications: fire with short timeouts, don't block user
        // Content service notification
        try {
            $this->contentService->notifyWorkoutCompletion($workoutCompletionData, $token);
        } catch (\Exception $e) {
            \Log::warning('Content notification failed (non-blocking)', ['error' => $e->getMessage()]);
        }

        // Engagement service notification
        try {
            $engagementData = [
                'user_id' => $session->user_id,
                'event_type' => 'workout_completed',
                'workout_id' => $session->workout_id,
                'session_data' => $workoutCompletionData,
                'timestamp' => now()->toISOString()
            ];
            $this->engagementService->recordWorkoutEngagement($engagementData, $token);
        } catch (\Exception $e) {
            \Log::warning('Engagement notification failed (non-blocking)', ['error' => $e->getMessage()]);
        }

        // ML behavioral data: least critical, skip if slow
        try {
            $behavioralData = [
                'user_id' => $session->user_id,
                'activity_type' => 'workout_completion',
                'session_metrics' => $workoutCompletionData,
                'user_profile' => $user,
                'timestamp' => now()->toISOString()
            ];
            $this->mlService->sendUserBehavioralData($behavioralData, $token);
        } catch (\Exception $e) {
            \Log::warning('ML notification failed (non-blocking)', ['error' => $e->getMessage()]);
        }

        // Progression: critical for user experience (level-ups)
        try {
            $progressionData = [
                'completed' => $session->is_completed,
                'duration_minutes' => $session->actual_duration_minutes,
                'difficulty' => $session->difficulty_level ?? 1,
                'is_group_workout' => $session->session_type === 'group',
            ];
            $this->progressionService->updateProgressionMetrics($session->user_id, $progressionData);

            $eligibility = $this->progressionService->checkPromotionEligibility($session->user_id);
            if ($eligibility['eligible']) {
                $this->progressionService->promoteUser($session->user_id, $eligibility['newLevel']);
                \Log::info('User auto-promoted', [
                    'user_id' => $session->user_id,
                    'new_level' => $eligibility['newLevel']
                ]);
            }
        } catch (\Exception $e) {
            \Log::warning('Progression notification failed', ['error' => $e->getMessage()]);
        }
    }
}