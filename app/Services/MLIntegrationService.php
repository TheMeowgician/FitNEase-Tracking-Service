<?php

namespace App\Services;

use App\Models\WorkoutSession;
use App\Models\WorkoutRating;
use App\Models\UserExerciseHistory;
use App\Models\ProgressTracking;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class MLIntegrationService
{
    private $client;
    private $mlServiceUrl;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);
        $this->mlServiceUrl = env('ML_SERVICE_URL', 'http://localhost:8001');
    }

    public function sendBehavioralDataToML($userId): bool
    {
        try {
            $behavioralData = [
                'user_id' => $userId,
                'workout_ratings' => $this->getUserWorkoutRatings($userId),
                'exercise_history' => $this->getUserExerciseHistory($userId),
                'performance_trends' => $this->getPerformanceTrends($userId),
                'completion_patterns' => $this->getCompletionPatterns($userId)
            ];

            $response = $this->client->post($this->mlServiceUrl . '/api/v1/behavioral-data', [
                'json' => $behavioralData
            ]);

            Log::info('Successfully sent behavioral data to ML service', [
                'user_id' => $userId,
                'response_status' => $response->getStatusCode()
            ]);

            return $response->getStatusCode() === 200;

        } catch (RequestException $e) {
            Log::error('Failed to send behavioral data to ML service', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'response' => $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null
            ]);
            return false;
        }
    }

    public function processUserDataForML($userId): array
    {
        return [
            'workout_patterns' => $this->getWorkoutPatterns($userId),
            'performance_trends' => $this->getPerformanceTrends($userId),
            'rating_preferences' => $this->getRatingPreferences($userId),
            'exercise_preferences' => $this->getExercisePreferences($userId),
            'completion_behavior' => $this->getCompletionBehavior($userId)
        ];
    }

    public function getWorkoutPatterns($userId)
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

    public function getPerformanceTrends($userId)
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

    public function getRatingPreferences($userId)
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

    public function getExercisePreferences($userId)
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

    public function getCompletionBehavior($userId)
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
            ->get()
            ->toArray();
    }

    private function getUserExerciseHistory($userId)
    {
        return UserExerciseHistory::where('user_id', $userId)
            ->with('workoutSession')
            ->latest()
            ->limit(200)
            ->get()
            ->toArray();
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
            ->get()
            ->toArray();
    }

    public function validateMLServiceConnection(): bool
    {
        try {
            $response = $this->client->get($this->mlServiceUrl . '/health');
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            Log::error('ML Service connection failed', [
                'error' => $e->getMessage(),
                'url' => $this->mlServiceUrl
            ]);
            return false;
        }
    }

    public function sendWorkoutCompletionData($sessionId): bool
    {
        try {
            $session = WorkoutSession::with(['workoutRatings', 'userExerciseHistory'])
                ->findOrFail($sessionId);

            $data = [
                'session_id' => $sessionId,
                'user_id' => $session->user_id,
                'workout_id' => $session->workout_id,
                'completion_data' => $session->toArray()
            ];

            $response = $this->client->post($this->mlServiceUrl . '/api/v1/workout-completion', [
                'json' => $data
            ]);

            return $response->getStatusCode() === 200;

        } catch (\Exception $e) {
            Log::error('Failed to send workout completion data to ML service', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}