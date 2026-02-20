<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EngagementService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = env('ENGAGEMENT_SERVICE_URL', 'http://localhost:8003');
    }

    public function recordWorkoutEngagement($engagementData, $token)
    {
        try {
            Log::info('Recording workout engagement', [
                'service' => 'fitnease-tracking',
                'user_id' => $engagementData['user_id'] ?? 'unknown',
                'engagement_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/workout-engagement', $engagementData);

            if ($response->successful()) {
                Log::info('Workout engagement recorded successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $engagementData['user_id'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to record workout engagement', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'engagement_data' => $engagementData,
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function updateProgressMilestone($milestoneData, $token)
    {
        try {
            Log::info('Updating progress milestone', [
                'service' => 'fitnease-tracking',
                'user_id' => $milestoneData['user_id'] ?? 'unknown',
                'milestone_type' => $milestoneData['milestone_type'] ?? 'unknown',
                'engagement_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/progress-milestone', $milestoneData);

            if ($response->successful()) {
                Log::info('Progress milestone updated successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $milestoneData['user_id'] ?? 'unknown',
                    'milestone_type' => $milestoneData['milestone_type'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to update progress milestone', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'milestone_data' => $milestoneData,
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function triggerAchievementNotification($achievementData, $token)
    {
        try {
            Log::info('Triggering achievement notification', [
                'service' => 'fitnease-tracking',
                'user_id' => $achievementData['user_id'] ?? 'unknown',
                'achievement_type' => $achievementData['achievement_type'] ?? 'unknown',
                'engagement_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/achievement-notification', $achievementData);

            if ($response->successful()) {
                Log::info('Achievement notification triggered successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $achievementData['user_id'] ?? 'unknown',
                    'achievement_type' => $achievementData['achievement_type'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to trigger achievement notification', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'achievement_data' => $achievementData,
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function updateStreakData($streakData, $token)
    {
        try {
            Log::info('Updating streak data', [
                'service' => 'fitnease-tracking',
                'user_id' => $streakData['user_id'] ?? 'unknown',
                'streak_type' => $streakData['streak_type'] ?? 'unknown',
                'engagement_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->post($this->baseUrl . '/api/engagement/streak-data', $streakData);

            if ($response->successful()) {
                Log::info('Streak data updated successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $streakData['user_id'] ?? 'unknown',
                    'streak_type' => $streakData['streak_type'] ?? 'unknown'
                ]);

                return $response->json();
            }

            Log::warning('Failed to update streak data', [
                'service' => 'fitnease-tracking',
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-tracking',
                'error' => $e->getMessage(),
                'streak_data' => $streakData,
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }

    public function getEngagementMetrics($userId, $token)
    {
        try {
            Log::info('Requesting engagement metrics', [
                'service' => 'fitnease-tracking',
                'user_id' => $userId,
                'engagement_service_url' => $this->baseUrl
            ]);

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])->get($this->baseUrl . '/api/engagement/metrics/' . $userId);

            if ($response->successful()) {
                $metricsData = $response->json();

                Log::info('Engagement metrics retrieved successfully', [
                    'service' => 'fitnease-tracking',
                    'user_id' => $userId
                ]);

                return $metricsData;
            }

            Log::warning('Failed to retrieve engagement metrics', [
                'service' => 'fitnease-tracking',
                'user_id' => $userId,
                'status' => $response->status(),
                'response_body' => $response->body()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Engagement service communication error', [
                'service' => 'fitnease-tracking',
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'engagement_service_url' => $this->baseUrl
            ]);

            return null;
        }
    }
}