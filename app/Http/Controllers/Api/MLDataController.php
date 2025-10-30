<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use App\Models\WorkoutRating;
use App\Models\UserExerciseHistory;
use App\Models\ProgressTracking;
use App\Models\WorkoutExerciseRating;
use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class MLDataController extends Controller
{
    protected MLService $mlService;

    public function __construct(MLService $mlService)
    {
        $this->mlService = $mlService;
    }
    public function getAllUserData(): JsonResponse
    {
        try {
            $data = [
                'workout_sessions' => WorkoutSession::with(['workoutRatings', 'userExerciseHistory'])->get(),
                'workout_ratings' => WorkoutRating::all(),
                'exercise_history' => UserExerciseHistory::all(),
                'progress_tracking' => ProgressTracking::all()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Bulk data export retrieved successfully',
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bulk data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserPatterns($userId): JsonResponse
    {
        try {
            $patterns = [
                'workout_patterns' => $this->getWorkoutPatterns($userId),
                'performance_trends' => $this->getPerformanceTrends($userId),
                'rating_preferences' => $this->getRatingPreferences($userId),
                'exercise_preferences' => $this->getExercisePreferences($userId),
                'completion_behavior' => $this->getCompletionBehavior($userId)
            ];

            return response()->json([
                'success' => true,
                'message' => 'User behavior patterns retrieved successfully',
                'data' => $patterns
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user patterns',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function sendBehavioralData(Request $request): JsonResponse
    {
        try {
            $userId = $request->input('user_id');

            $behavioralData = [
                'user_id' => $userId,
                'workout_ratings' => $this->getUserWorkoutRatings($userId),
                'exercise_history' => $this->getUserExerciseHistory($userId),
                'performance_trends' => $this->getPerformanceTrends($userId),
                'completion_patterns' => $this->getCompletionPatterns($userId)
            ];

            $token = $request->bearerToken();

            $result = $this->mlService->sendUserBehavioralData($behavioralData, $token);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Behavioral data sent to ML service successfully',
                    'ml_response' => $result
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send behavioral data to ML service'
                ], 503);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send behavioral data to ML service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getWorkoutPatterns($userId)
    {
        return WorkoutSession::where('user_id', $userId)
            ->selectRaw('
                HOUR(start_time) as preferred_hour,
                DAYOFWEEK(created_at) as preferred_day,
                AVG(actual_duration_minutes) as avg_duration,
                COUNT(*) as frequency
            ')
            ->groupBy(['preferred_hour', 'preferred_day'])
            ->get();
    }

    private function getPerformanceTrends($userId)
    {
        return WorkoutSession::where('user_id', $userId)
            ->selectRaw('
                DATE(created_at) as workout_date,
                AVG(performance_rating) as avg_performance,
                AVG(completion_percentage) as avg_completion,
                COUNT(*) as session_count
            ')
            ->groupBy('workout_date')
            ->orderBy('workout_date', 'desc')
            ->limit(30)
            ->get();
    }

    private function getRatingPreferences($userId)
    {
        return WorkoutRating::where('user_id', $userId)
            ->join('workouts', 'workout_ratings.workout_id', '=', 'workouts.workout_id')
            ->selectRaw('
                workouts.difficulty_level,
                workouts.target_muscle_groups,
                AVG(rating_value) as avg_rating,
                COUNT(*) as rating_count
            ')
            ->groupBy(['workouts.difficulty_level', 'workouts.target_muscle_groups'])
            ->get();
    }

    private function getExercisePreferences($userId)
    {
        return UserExerciseHistory::where('user_id', $userId)
            ->join('exercises', 'user_exercise_history.exercise_id', '=', 'exercises.exercise_id')
            ->selectRaw('
                exercises.target_muscle_group,
                exercises.difficulty_level,
                AVG(performance_score) as avg_performance,
                COUNT(*) as frequency,
                AVG(CASE WHEN difficulty_perceived = "very_easy" THEN 1
                         WHEN difficulty_perceived = "easy" THEN 2
                         WHEN difficulty_perceived = "moderate" THEN 3
                         WHEN difficulty_perceived = "hard" THEN 4
                         WHEN difficulty_perceived = "very_hard" THEN 5
                         ELSE 3 END) as perceived_difficulty
            ')
            ->groupBy(['exercises.target_muscle_group', 'exercises.difficulty_level'])
            ->having('frequency', '>=', 3)
            ->get();
    }

    private function getCompletionBehavior($userId)
    {
        return WorkoutSession::where('user_id', $userId)
            ->selectRaw('
                session_type,
                AVG(completion_percentage) as avg_completion,
                SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_count,
                COUNT(*) as total_count
            ')
            ->groupBy('session_type')
            ->get();
    }

    private function getUserWorkoutRatings($userId)
    {
        return WorkoutRating::where('user_id', $userId)
            ->with('workoutSession')
            ->latest()
            ->limit(100)
            ->get();
    }

    private function getUserExerciseHistory($userId)
    {
        return UserExerciseHistory::where('user_id', $userId)
            ->with('workoutSession')
            ->latest()
            ->limit(200)
            ->get();
    }

    private function getCompletionPatterns($userId)
    {
        return WorkoutSession::where('user_id', $userId)
            ->selectRaw('
                HOUR(start_time) as hour_of_day,
                DAYOFWEEK(created_at) as day_of_week,
                AVG(completion_percentage) as avg_completion,
                COUNT(*) as session_count
            ')
            ->groupBy(['hour_of_day', 'day_of_week'])
            ->having('session_count', '>=', 2)
            ->get();
    }

    /**
     * Get exercise ratings for ML collaborative filtering
     * This is THE CRITICAL ENDPOINT for enabling collaborative filtering
     *
     * GET /api/tracking/ml-data/exercise-ratings
     *
     * Query params:
     * - user_id (optional): Filter by specific user
     * - since (optional): Get ratings since a specific date
     * - limit (optional): Limit number of results (default 10000)
     *
     * Returns: Array of [user_id, exercise_id, rating_value, rated_at]
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getExerciseRatings(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'nullable|integer',
                'since' => 'nullable|date',
                'limit' => 'nullable|integer|max:50000',
            ]);

            $query = WorkoutExerciseRating::query()
                ->select('user_id', 'exercise_id', 'rating_value', 'rated_at',
                         'difficulty_perceived', 'enjoyment_rating', 'would_do_again')
                ->completed(); // Only completed exercises

            // Filter by user if specified
            if (isset($validated['user_id'])) {
                $query->forUser($validated['user_id']);
            }

            // Filter by date if specified
            if (isset($validated['since'])) {
                $query->where('rated_at', '>=', $validated['since']);
            }

            // Apply limit
            $limit = $validated['limit'] ?? 10000;
            $query->limit($limit);

            // Order by most recent first
            $ratings = $query->latestFirst()->get();

            // Format for ML service
            $formattedRatings = $ratings->map(function ($rating) {
                return [
                    'user_id' => (int) $rating->user_id,
                    'exercise_id' => (int) $rating->exercise_id,
                    'rating' => (float) $rating->rating_value,
                    'rated_at' => $rating->rated_at->toISOString(),
                    'difficulty' => $rating->difficulty_perceived,
                    'enjoyment' => $rating->enjoyment_rating ? (float) $rating->enjoyment_rating : null,
                    'would_do_again' => $rating->would_do_again,
                ];
            });

            Log::info('ML Service fetched exercise ratings', [
                'count' => $formattedRatings->count(),
                'user_id' => $validated['user_id'] ?? 'all',
                'since' => $validated['since'] ?? 'all time',
            ]);

            return response()->json([
                'success' => true,
                'count' => $formattedRatings->count(),
                'data' => $formattedRatings,
                'metadata' => [
                    'fetched_at' => now()->toISOString(),
                    'filters' => [
                        'user_id' => $validated['user_id'] ?? null,
                        'since' => $validated['since'] ?? null,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch exercise ratings for ML service', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch exercise ratings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's exercise ratings for ML service
     *
     * GET /api/tracking/ml-data/user-ratings/{userId}
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getUserExerciseRatings($userId): JsonResponse
    {
        try {
            $ratings = WorkoutExerciseRating::forUser($userId)
                ->completed()
                ->select('exercise_id', 'rating_value', 'rated_at',
                         'difficulty_perceived', 'enjoyment_rating')
                ->latestFirst()
                ->get();

            // Format as dictionary for ML collaborative filtering
            $ratingsDict = $ratings->mapWithKeys(function ($rating) {
                return [$rating->exercise_id => (float) $rating->rating_value];
            });

            Log::info('ML Service fetched user exercise ratings', [
                'user_id' => $userId,
                'count' => $ratings->count(),
            ]);

            return response()->json([
                'success' => true,
                'user_id' => (int) $userId,
                'count' => $ratings->count(),
                'ratings' => $ratingsDict,
                'ratings_list' => $ratings->map(function ($rating) {
                    return [
                        'exercise_id' => (int) $rating->exercise_id,
                        'rating' => (float) $rating->rating_value,
                        'rated_at' => $rating->rated_at->toISOString(),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch user exercise ratings for ML service', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user exercise ratings',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get rating statistics for ML model training
     *
     * GET /api/tracking/ml-data/rating-stats
     *
     * @return JsonResponse
     */
    public function getRatingStatistics(): JsonResponse
    {
        try {
            $stats = [
                'total_ratings' => WorkoutExerciseRating::completed()->count(),
                'unique_users' => WorkoutExerciseRating::completed()->distinct('user_id')->count(),
                'unique_exercises' => WorkoutExerciseRating::completed()->distinct('exercise_id')->count(),
                'average_rating' => WorkoutExerciseRating::completed()->avg('rating_value'),
                'rating_distribution' => [
                    '5_stars' => WorkoutExerciseRating::completed()->where('rating_value', '>=', 4.5)->count(),
                    '4_stars' => WorkoutExerciseRating::completed()
                        ->where('rating_value', '>=', 3.5)
                        ->where('rating_value', '<', 4.5)
                        ->count(),
                    '3_stars' => WorkoutExerciseRating::completed()
                        ->where('rating_value', '>=', 2.5)
                        ->where('rating_value', '<', 3.5)
                        ->count(),
                    '2_stars' => WorkoutExerciseRating::completed()
                        ->where('rating_value', '>=', 1.5)
                        ->where('rating_value', '<', 2.5)
                        ->count(),
                    '1_star' => WorkoutExerciseRating::completed()->where('rating_value', '<', 1.5)->count(),
                ],
                'data_quality' => [
                    'ratings_with_difficulty' => WorkoutExerciseRating::completed()
                        ->whereNotNull('difficulty_perceived')
                        ->count(),
                    'ratings_with_enjoyment' => WorkoutExerciseRating::completed()
                        ->whereNotNull('enjoyment_rating')
                        ->count(),
                    'ratings_with_notes' => WorkoutExerciseRating::completed()
                        ->whereNotNull('notes')
                        ->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch rating statistics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rating statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}