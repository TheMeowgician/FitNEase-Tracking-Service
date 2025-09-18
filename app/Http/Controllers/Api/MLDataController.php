<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use App\Models\WorkoutRating;
use App\Models\UserExerciseHistory;
use App\Models\ProgressTracking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use GuzzleHttp\Client;

class MLDataController extends Controller
{
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

            // Send to ML service
            $mlServiceUrl = env('ML_SERVICE_URL', 'http://localhost:8001');
            $client = new Client();

            $response = $client->post($mlServiceUrl . '/api/v1/behavioral-data', [
                'json' => $behavioralData,
                'timeout' => 30,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json'
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Behavioral data sent to ML service successfully',
                'ml_response_status' => $response->getStatusCode()
            ]);

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
            ->selectRaw('
                workout_id,
                AVG(rating_value) as avg_rating,
                AVG(enjoyment_rating) as avg_enjoyment,
                COUNT(*) as rating_count,
                SUM(CASE WHEN would_recommend = 1 THEN 1 ELSE 0 END) as recommend_count
            ')
            ->groupBy('workout_id')
            ->having('rating_count', '>=', 2)
            ->get();
    }

    private function getExercisePreferences($userId)
    {
        return UserExerciseHistory::where('user_id', $userId)
            ->selectRaw('
                exercise_id,
                AVG(performance_score) as avg_performance,
                COUNT(*) as frequency,
                AVG(CASE WHEN difficulty_perceived = "very_easy" THEN 1
                         WHEN difficulty_perceived = "easy" THEN 2
                         WHEN difficulty_perceived = "moderate" THEN 3
                         WHEN difficulty_perceived = "hard" THEN 4
                         WHEN difficulty_perceived = "very_hard" THEN 5
                         ELSE 3 END) as perceived_difficulty
            ')
            ->groupBy('exercise_id')
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
}