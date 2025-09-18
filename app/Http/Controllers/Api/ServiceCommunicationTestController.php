<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentService;
use App\Services\EngagementService;
use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class ServiceCommunicationTestController extends Controller
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

    public function testServiceConnectivity(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $services = [
                'auth' => env('AUTH_SERVICE_URL', 'http://localhost:8000'),
                'content' => env('CONTENT_SERVICE_URL', 'http://localhost:8002'),
                'engagement' => env('ENGAGEMENT_SERVICE_URL', 'http://localhost:8003'),
                'ml' => env('ML_SERVICE_URL', 'http://localhost:8001')
            ];

            $connectivity = [];

            foreach ($services as $serviceName => $serviceUrl) {
                try {
                    $response = Http::timeout(10)->withHeaders([
                        'Authorization' => 'Bearer ' . $token,
                        'Accept' => 'application/json'
                    ])->get($serviceUrl . '/health');

                    $connectivity[$serviceName] = [
                        'url' => $serviceUrl,
                        'status' => $response->successful() ? 'connected' : 'failed',
                        'response_code' => $response->status(),
                        'response_time' => $response->handlerStats()['total_time'] ?? 'unknown'
                    ];

                } catch (\Exception $e) {
                    $connectivity[$serviceName] = [
                        'url' => $serviceUrl,
                        'status' => 'error',
                        'error' => $e->getMessage()
                    ];
                }
            }

            $overallHealth = true;
            foreach ($connectivity as $service) {
                if ($service['status'] !== 'connected') {
                    $overallHealth = false;
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Service connectivity test completed',
                'overall_health' => $overallHealth ? 'healthy' : 'degraded',
                'services' => $connectivity,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service connectivity test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testAuthTokenValidation(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'No authentication token provided'
                ], 401);
            }

            $authServiceUrl = env('AUTH_SERVICE_URL', 'http://localhost:8000');

            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            ])->get($authServiceUrl . '/api/auth/user');

            if ($response->successful()) {
                $userData = $response->json();

                return response()->json([
                    'success' => true,
                    'message' => 'Token validation successful',
                    'auth_service_status' => 'connected',
                    'user_data' => $userData,
                    'timestamp' => now()->toISOString()
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Token validation failed',
                    'auth_service_status' => 'failed',
                    'response_code' => $response->status(),
                    'response_body' => $response->body()
                ], $response->status());
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Auth service communication error',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testDataFlow(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            $user = $request->attributes->get('user');

            if (!$token || !$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $userId = $user['id'];
            $workoutId = $request->input('workout_id', 1);
            $exerciseId = $request->input('exercise_id', 1);

            $testResults = [];

            $workoutData = $this->contentService->getWorkoutDetails($workoutId, $token);
            $testResults['content_service'] = [
                'workout_details' => $workoutData ? 'success' : 'failed',
                'data_received' => !empty($workoutData)
            ];

            $engagementData = [
                'user_id' => $userId,
                'event_type' => 'test_data_flow',
                'workout_id' => $workoutId,
                'session_data' => [
                    'completion_percentage' => 100,
                    'performance_rating' => 4.5
                ],
                'timestamp' => now()->toISOString()
            ];

            $engagementResult = $this->engagementService->recordWorkoutEngagement($engagementData, $token);
            $testResults['engagement_service'] = [
                'engagement_recorded' => $engagementResult ? 'success' : 'failed',
                'data_sent' => !empty($engagementData)
            ];

            $behavioralData = [
                'user_id' => $userId,
                'activity_type' => 'test_data_flow',
                'session_metrics' => [
                    'completion_percentage' => 100,
                    'performance_rating' => 4.5,
                    'calories_burned' => 300
                ],
                'user_profile' => $user,
                'timestamp' => now()->toISOString()
            ];

            $mlResult = $this->mlService->sendUserBehavioralData($behavioralData, $token);
            $testResults['ml_service'] = [
                'behavioral_data_sent' => $mlResult ? 'success' : 'failed',
                'data_processed' => !empty($mlResult)
            ];

            $overallSuccess = true;
            foreach ($testResults as $service) {
                foreach ($service as $test => $status) {
                    if ($status === 'failed' || $status === false) {
                        $overallSuccess = false;
                        break 2;
                    }
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => $overallSuccess ? 'Data flow test completed successfully' : 'Data flow test encountered issues',
                'test_results' => $testResults,
                'test_data' => [
                    'user_id' => $userId,
                    'workout_id' => $workoutId,
                    'exercise_id' => $exerciseId
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data flow test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function testEndToEndWorkflow(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            $user = $request->attributes->get('user');

            if (!$token || !$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $userId = $user['id'];
            $workflowResults = [];

            $exerciseDetails = $this->contentService->getExerciseDetails(1, $token);
            $workflowResults['step_1_get_exercise'] = [
                'status' => $exerciseDetails ? 'success' : 'failed',
                'service' => 'content'
            ];

            $workoutCompletion = [
                'user_id' => $userId,
                'workout_id' => 1,
                'session_id' => 999,
                'completion_percentage' => 100,
                'performance_rating' => 4.5,
                'calories_burned' => 300,
                'duration_minutes' => 45,
                'completed_at' => now()->toISOString()
            ];

            $contentNotification = $this->contentService->notifyWorkoutCompletion($workoutCompletion, $token);
            $workflowResults['step_2_notify_content'] = [
                'status' => $contentNotification ? 'success' : 'failed',
                'service' => 'content'
            ];

            $engagementData = [
                'user_id' => $userId,
                'event_type' => 'workout_completed',
                'workout_id' => 1,
                'session_data' => $workoutCompletion,
                'timestamp' => now()->toISOString()
            ];

            $engagementResult = $this->engagementService->recordWorkoutEngagement($engagementData, $token);
            $workflowResults['step_3_record_engagement'] = [
                'status' => $engagementResult ? 'success' : 'failed',
                'service' => 'engagement'
            ];

            $behavioralData = [
                'user_id' => $userId,
                'activity_type' => 'workout_completion',
                'session_metrics' => $workoutCompletion,
                'user_profile' => $user,
                'timestamp' => now()->toISOString()
            ];

            $mlResult = $this->mlService->sendUserBehavioralData($behavioralData, $token);
            $workflowResults['step_4_send_ml_data'] = [
                'status' => $mlResult ? 'success' : 'failed',
                'service' => 'ml'
            ];

            $recommendations = $this->mlService->getWorkoutRecommendations($userId, $token);
            $workflowResults['step_5_get_recommendations'] = [
                'status' => $recommendations ? 'success' : 'failed',
                'service' => 'ml'
            ];

            $workflowSuccess = true;
            foreach ($workflowResults as $step) {
                if ($step['status'] === 'failed') {
                    $workflowSuccess = false;
                    break;
                }
            }

            return response()->json([
                'success' => $workflowSuccess,
                'message' => $workflowSuccess ? 'End-to-end workflow test completed successfully' : 'End-to-end workflow test failed',
                'workflow_steps' => $workflowResults,
                'test_scenario' => 'Complete workout session with service notifications',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'End-to-end workflow test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}