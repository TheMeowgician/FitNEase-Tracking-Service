<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ContentService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('CONTENT_SERVICE_URL', 'http://localhost:8002');
    }

    public function getExerciseDetails($exerciseId, $token)
    {
        try {
            Log::info('Requesting exercise details from content service', [
                'service' => 'fitnease-tracking',
                'exercise_id' => $exerciseId,
                'content_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/content/exercises/' . $exerciseId);

            if ($response->successful()) {
                $exerciseData = $response->json();

                Log::info('Exercise details retrieved successfully', [
                    'service' => 'fitnease-tracking',
                    'exercise_id' => $exerciseId,
                    'exercise_name' => $exerciseData['name'] ?? 'unknown'
                ]);

                return $exerciseData;
            }

            Log::warning('Failed to retrieve exercise details', [
                'service' => 'fitnease-tracking',
                'exercise_id' => $exerciseId,
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Content service communication error', [
                'service' => 'fitnease-tracking',
                'exercise_id' => $exerciseId,
                'error' => $e->getMessage(),
                'content_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function getWorkoutDetails($workoutId, $token)
    {
        try {
            Log::info('Requesting workout details from content service', [
                'service' => 'fitnease-tracking',
                'workout_id' => $workoutId,
                'content_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/content/workouts/' . $workoutId);

            if ($response->successful()) {
                $workoutData = $response->json();

                Log::info('Workout details retrieved successfully', [
                    'service' => 'fitnease-tracking',
                    'workout_id' => $workoutId,
                    'workout_name' => $workoutData['name'] ?? 'unknown'
                ]);

                return $workoutData;
            }

            Log::warning('Failed to retrieve workout details', [
                'service' => 'fitnease-tracking',
                'workout_id' => $workoutId,
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Content service communication error', [
                'service' => 'fitnease-tracking',
                'workout_id' => $workoutId,
                'error' => $e->getMessage(),
                'content_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function notifyWorkoutCompletion($workoutCompletionData, $token)
    {
        try {
            Log::info('Notifying content service of workout completion', [
                'service' => 'fitnease-tracking',
                'user_id' => $workoutCompletionData['user_id'] ?? 'unknown',
                'workout_id' => $workoutCompletionData['workout_id'] ?? 'unknown',
                'content_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/content/workout-completion', $workoutCompletionData);

            if ($response->successful()) {
                Log::info('Workout completion notification sent successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $workoutCompletionData['user_id'] ?? 'unknown',
                    'workout_id' => $workoutCompletionData['workout_id'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to notify workout completion', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Content service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'workout_completion_data' => $workoutCompletionData,
                'content_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function updateExercisePopularity($exercisePopularityData, $token)
    {
        try {
            Log::info('Updating exercise popularity in content service', [
                'service' => 'fitnease-tracking',
                'exercise_id' => $exercisePopularityData['exercise_id'] ?? 'unknown',
                'content_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(30)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/content/exercise-popularity', $exercisePopularityData);

            if ($response->successful()) {
                Log::info('Exercise popularity updated successfully', [
                    'service' => 'fitnease-tracking',
                    'exercise_id' => $exercisePopularityData['exercise_id'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to update exercise popularity', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Content service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'exercise_popularity_data' => $exercisePopularityData,
                'content_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }
}