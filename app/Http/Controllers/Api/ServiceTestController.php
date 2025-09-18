<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentService;
use App\Services\EngagementService;
use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceTestController extends Controller
{
    protected ContentService $contentService;
    protected EngagementService $engagementService;
    protected MLService $mlService;

    public function __construct(
        ContentService $contentService,
        EngagementService $engagementService,
        MLService $mlService
    ) {
        $this->contentService = $contentService;
        $this->engagementService = $engagementService;
        $this->mlService = $mlService;
    }

    public function testContentService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $exerciseId = $request->input('exercise_id', 1);
            $workoutId = $request->input('workout_id', 1);

            $tests = [
                'exercise_details' => $this->contentService->getExerciseDetails($exerciseId, $token),
                'workout_details' => $this->contentService->getWorkoutDetails($workoutId, $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Content service test completed',
                'service' => 'content',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Content service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testEngagementService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['id'] ?? 1;

            $engagementData = [
                'user_id' => $userId,
                'event_type' => 'test_workout_completed',
                'workout_id' => 1,
                'session_data' => [
                    'completion_percentage' => 100,
                    'performance_rating' => 4.5,
                    'calories_burned' => 250,
                    'duration_minutes' => 45
                ],
                'timestamp' => now()->toISOString()
            ];

            $milestoneData = [
                'user_id' => $userId,
                'milestone_type' => 'test_milestone',
                'value' => 10,
                'description' => 'Test milestone achievement',
                'timestamp' => now()->toISOString()
            ];

            $tests = [
                'workout_engagement' => $this->engagementService->recordWorkoutEngagement($engagementData, $token),
                'progress_milestone' => $this->engagementService->updateProgressMilestone($milestoneData, $token),
                'engagement_metrics' => $this->engagementService->getEngagementMetrics($userId, $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'Engagement service test completed',
                'service' => 'engagement',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Engagement service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testMLService(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $user = $request->attributes->get('user');
            $userId = $user['id'] ?? 1;

            $behavioralData = [
                'user_id' => $userId,
                'activity_type' => 'test_data',
                'session_metrics' => [
                    'completion_percentage' => 100,
                    'performance_rating' => 4.5,
                    'calories_burned' => 250,
                    'duration_minutes' => 45
                ],
                'user_profile' => $user,
                'timestamp' => now()->toISOString()
            ];

            $userMetrics = [
                'user_id' => $userId,
                'recent_sessions' => [
                    ['performance_rating' => 4.5, 'completion_percentage' => 100],
                    ['performance_rating' => 4.0, 'completion_percentage' => 95],
                    ['performance_rating' => 4.2, 'completion_percentage' => 100]
                ],
                'timestamp' => now()->toISOString()
            ];

            $tests = [
                'behavioral_data' => $this->mlService->sendUserBehavioralData($behavioralData, $token),
                'workout_recommendations' => $this->mlService->getWorkoutRecommendations($userId, $token),
                'overtraining_prediction' => $this->mlService->predictOvertrainingRisk($userMetrics, $token)
            ];

            return response()->json([
                'success' => true,
                'message' => 'ML service test completed',
                'service' => 'ml',
                'results' => $tests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ML service test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testAllServices(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $allTests = [
                'content_service' => $this->testContentService($request)->getData(),
                'engagement_service' => $this->testEngagementService($request)->getData(),
                'ml_service' => $this->testMLService($request)->getData()
            ];

            $overallSuccess = true;
            foreach ($allTests as $test) {
                if (!$test->success) {
                    $overallSuccess = false;
                    break;
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'All service tests completed successfully' : 'Some service tests failed',
                'results' => $allTests,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service testing failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}