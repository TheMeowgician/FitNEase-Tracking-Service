<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RecommendationInteraction;
use App\Models\UserExerciseHistory;
use App\Models\WorkoutRating;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecommendationTrackingController extends Controller
{
    /**
     * Track when recommendations are shown to user
     */
    public function trackRecommendationsShown(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'session_id' => 'required|string|max:255',
            'source_exercise_id' => 'nullable|integer',
            'recommended_exercises' => 'required|array|min:1|max:20',
            'recommended_exercises.*' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interactions = [];
            $rank = 1;

            foreach ($request->recommended_exercises as $exerciseId) {
                $interactions[] = [
                    'user_id' => $request->user_id,
                    'recommended_exercise_id' => $exerciseId,
                    'source_exercise_id' => $request->source_exercise_id,
                    'recommendation_session_id' => $request->session_id,
                    'recommendation_rank' => $rank,
                    'was_clicked' => false,
                    'was_completed' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                $rank++;
            }

            RecommendationInteraction::insert($interactions);

            return response()->json([
                'success' => true,
                'message' => 'Recommendations tracked successfully',
                'session_id' => $request->session_id,
                'tracked_count' => count($interactions)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track recommendations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track when user clicks on a recommendation
     */
    public function trackRecommendationClick(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'session_id' => 'required|string|max:255',
            'exercise_id' => 'required|integer',
            'rank' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $interaction = RecommendationInteraction::where([
                'user_id' => $request->user_id,
                'recommendation_session_id' => $request->session_id,
                'recommended_exercise_id' => $request->exercise_id,
            ]);

            if ($request->rank) {
                $interaction->where('recommendation_rank', $request->rank);
            }

            $updated = $interaction->update(['was_clicked' => true]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Recommendation interaction not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Recommendation click tracked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track recommendation click',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track when user completes a recommended exercise
     */
    public function trackRecommendationCompletion(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'session_id' => 'required|string|max:255',
            'exercise_id' => 'required|integer',
            'history_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update recommendation interaction
            $interaction = RecommendationInteraction::where([
                'user_id' => $request->user_id,
                'recommendation_session_id' => $request->session_id,
                'recommended_exercise_id' => $request->exercise_id,
            ])->first();

            if (!$interaction) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Recommendation interaction not found'
                ], 404);
            }

            $interaction->update([
                'was_completed' => true,
                'completion_tracked_in_history_id' => $request->history_id
            ]);

            // Update UserExerciseHistory to link it to recommendation
            UserExerciseHistory::where('history_id', $request->history_id)->update([
                'came_from_recommendation' => true,
                'recommendation_session_id' => $request->session_id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Recommendation completion tracked successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to track recommendation completion',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Track workout rating from recommendation
     */
    public function trackRecommendationRating(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'session_id' => 'required|string|max:255',
            'rating_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $updated = WorkoutRating::where('rating_id', $request->rating_id)
                ->where('user_id', $request->user_id)
                ->update([
                    'came_from_recommendation' => true,
                    'recommendation_session_id' => $request->session_id
                ]);

            if (!$updated) {
                return response()->json([
                    'success' => false,
                    'message' => 'Workout rating not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Recommendation rating tracked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to track recommendation rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommendation analytics for a user
     */
    public function getUserRecommendationAnalytics(Request $request, $userId): JsonResponse
    {
        try {
            $days = $request->query('days', 30);

            $stats = RecommendationInteraction::getUserStats($userId, $days);

            return response()->json([
                'success' => true,
                'user_id' => $userId,
                'period_days' => $days,
                'analytics' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get session analytics
     */
    public function getSessionAnalytics(Request $request, $sessionId): JsonResponse
    {
        try {
            $stats = RecommendationInteraction::getSessionStats($sessionId);

            return response()->json([
                'success' => true,
                'session_id' => $sessionId,
                'analytics' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get session analytics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overall recommendation performance metrics
     */
    public function getOverallMetrics(Request $request): JsonResponse
    {
        try {
            $days = $request->query('days', 30);
            $startDate = Carbon::now()->subDays($days);

            $metrics = DB::select("
                SELECT
                    COUNT(DISTINCT ri.user_id) as total_users,
                    COUNT(DISTINCT ri.recommendation_session_id) as total_sessions,
                    COUNT(*) as total_recommendations_shown,
                    SUM(CASE WHEN ri.was_clicked = 1 THEN 1 ELSE 0 END) as total_clicked,
                    SUM(CASE WHEN ri.was_completed = 1 THEN 1 ELSE 0 END) as total_completed,
                    ROUND(
                        (SUM(CASE WHEN ri.was_clicked = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2
                    ) as overall_ctr,
                    ROUND(
                        (SUM(CASE WHEN ri.was_completed = 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2
                    ) as overall_completion_rate
                FROM recommendation_interactions ri
                WHERE ri.created_at >= ?
            ", [$startDate]);

            return response()->json([
                'success' => true,
                'period_days' => $days,
                'metrics' => $metrics[0] ?? null
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get overall metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}