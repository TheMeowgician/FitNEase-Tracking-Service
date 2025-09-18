<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MLService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('ML_SERVICE_URL', 'http://localhost:8001');
    }

    public function sendUserBehavioralData($behavioralData, $token)
    {
        try {
            Log::info('Sending user behavioral data to ML service', [
                'service' => 'fitnease-tracking',
                'user_id' => $behavioralData['user_id'] ?? 'unknown',
                'ml_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/ml/behavioral-data', $behavioralData);

            if ($response->successful()) {
                Log::info('Behavioral data sent successfully to ML service', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $behavioralData['user_id'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to send behavioral data to ML service', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ML service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'behavioral_data' => $behavioralData,
                'ml_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function getWorkoutRecommendations($userId, $token, $parameters = [])
    {
        try {
            Log::info('Requesting workout recommendations from ML service', [
                'service' => 'fitnease-tracking',
                'user_id' => $userId,
                'ml_service_url' => $this->baseUrl
            ]);

            $queryParams = array_merge(['user_id' => $userId], $parameters);

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/ml/workout-recommendations', $queryParams);

            if ($response->successful()) {
                $recommendations = $response->json();

                Log::info('Workout recommendations retrieved successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $userId,
                    'recommendation_count' => count($recommendations['recommendations'] ?? [])
                ]);

                return $recommendations;
            }

            Log::warning('Failed to retrieve workout recommendations', [
                'service' => 'fitnease-tracking',
                'user_id' => $userId,
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ML service communication error', [
                'service' => 'fitnease-tracking',
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'ml_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function analyzePerformancePatterns($performanceData, $token)
    {
        try {
            Log::info('Analyzing performance patterns with ML service', [
                'service' => 'fitnease-tracking',
                'user_id' => $performanceData['user_id'] ?? 'unknown',
                'ml_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/ml/performance-analysis', $performanceData);

            if ($response->successful()) {
                $analysis = $response->json();

                Log::info('Performance patterns analyzed successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $performanceData['user_id'] ?? 'unknown'
                ]);

                return $analysis;
            }

            Log::warning('Failed to analyze performance patterns', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ML service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'performance_data' => $performanceData,
                'ml_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function predictOvertrainingRisk($userMetrics, $token)
    {
        try {
            Log::info('Predicting overtraining risk with ML service', [
                'service' => 'fitnease-tracking',
                'user_id' => $userMetrics['user_id'] ?? 'unknown',
                'ml_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/ml/overtraining-prediction', $userMetrics);

            if ($response->successful()) {
                $prediction = $response->json();

                Log::info('Overtraining risk predicted successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $userMetrics['user_id'] ?? 'unknown',
                    'risk_level' => $prediction['risk_level'] ?? 'unknown'
                ]);

                return $prediction;
            }

            Log::warning('Failed to predict overtraining risk', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ML service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'user_metrics' => $userMetrics,
                'ml_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function generateInsights($insightRequest, $token)
    {
        try {
            Log::info('Generating insights with ML service', [
                'service' => 'fitnease-tracking',
                'user_id' => $insightRequest['user_id'] ?? 'unknown',
                'insight_type' => $insightRequest['type'] ?? 'unknown',
                'ml_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/ml/generate-insights', $insightRequest);

            if ($response->successful()) {
                $insights = $response->json();

                Log::info('Insights generated successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $insightRequest['user_id'] ?? 'unknown',
                    'insight_type' => $insightRequest['type'] ?? 'unknown'
                ]);

                return $insights;
            }

            Log::warning('Failed to generate insights', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ML service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'insight_request' => $insightRequest,
                'ml_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function updateUserModel($modelUpdateData, $token)
    {
        try {
            Log::info('Updating user model in ML service', [
                'service' => 'fitnease-tracking',
                'user_id' => $modelUpdateData['user_id'] ?? 'unknown',
                'ml_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/ml/update-user-model', $modelUpdateData);

            if ($response->successful()) {
                Log::info('User model updated successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $modelUpdateData['user_id'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to update user model', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('ML service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'model_update_data' => $modelUpdateData,
                'ml_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }
}