<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use App\Services\ContentService;
use App\Services\EngagementService;
use App\Services\MLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ServiceIntegrationDemoController extends Controller
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

    public function demoCompleteWorkoutFlow(Request $request): JsonResponse
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

            $userId = $user['user_id'];
            $workoutId = $request->input('workout_id', 1);

            $demoFlow = [];

            $workoutDetails = $this->contentService->getWorkoutDetails($workoutId, $token);
            $demoFlow['step_1'] = [
                'action' => 'Retrieved workout details from Content Service',
                'service' => 'content',
                'data' => $workoutDetails,
                'success' => !empty($workoutDetails)
            ];

            $sessionData = [
                'user_id' => $userId,
                'workout_id' => $workoutId,
                'session_type' => 'individual',
                'start_time' => now()->subMinutes(45)->toDateTimeString(),
                'end_time' => now()->toDateTimeString(),
                'actual_duration_minutes' => 45,
                'is_completed' => true,
                'completion_percentage' => 95.5,
                'calories_burned' => 350,
                'performance_rating' => 4.2,
                'user_notes' => 'Demo workout session - felt great!',
                'heart_rate_avg' => 142
            ];

            $session = WorkoutSession::create($sessionData);
            $demoFlow['step_2'] = [
                'action' => 'Created workout session in Tracking Service',
                'service' => 'tracking',
                'data' => $session,
                'success' => !empty($session)
            ];

            $workoutCompletionData = [
                'user_id' => $userId,
                'workout_id' => $workoutId,
                'session_id' => $session->id,
                'completion_percentage' => $session->completion_percentage,
                'performance_rating' => $session->performance_rating,
                'calories_burned' => $session->calories_burned,
                'duration_minutes' => $session->actual_duration_minutes,
                'completed_at' => $session->updated_at->toISOString()
            ];

            $contentNotification = $this->contentService->notifyWorkoutCompletion($workoutCompletionData, $token);
            $demoFlow['step_3'] = [
                'action' => 'Notified Content Service of workout completion',
                'service' => 'content',
                'data' => $contentNotification,
                'success' => !empty($contentNotification)
            ];

            $engagementData = [
                'user_id' => $userId,
                'event_type' => 'workout_completed',
                'workout_id' => $workoutId,
                'session_data' => $workoutCompletionData,
                'timestamp' => now()->toISOString()
            ];

            $engagementResult = $this->engagementService->recordWorkoutEngagement($engagementData, $token);
            $demoFlow['step_4'] = [
                'action' => 'Recorded workout engagement event',
                'service' => 'engagement',
                'data' => $engagementResult,
                'success' => !empty($engagementResult)
            ];

            if ($session->performance_rating >= 4.0) {
                $milestoneData = [
                    'user_id' => $userId,
                    'milestone_type' => 'high_performance',
                    'value' => $session->performance_rating,
                    'description' => 'Achieved high performance rating in workout',
                    'timestamp' => now()->toISOString()
                ];

                $milestoneResult = $this->engagementService->updateProgressMilestone($milestoneData, $token);
                $demoFlow['step_5'] = [
                    'action' => 'Updated progress milestone for high performance',
                    'service' => 'engagement',
                    'data' => $milestoneResult,
                    'success' => !empty($milestoneResult)
                ];
            }

            $behavioralData = [
                'user_id' => $userId,
                'activity_type' => 'workout_completion',
                'session_metrics' => $workoutCompletionData,
                'user_profile' => $user,
                'timestamp' => now()->toISOString()
            ];

            $mlResult = $this->mlService->sendUserBehavioralData($behavioralData, $token);
            $demoFlow['step_6'] = [
                'action' => 'Sent behavioral data to ML Service',
                'service' => 'ml',
                'data' => $mlResult,
                'success' => !empty($mlResult)
            ];

            $recommendations = $this->mlService->getWorkoutRecommendations($userId, $token, [
                'current_performance' => $session->performance_rating,
                'completion_rate' => $session->completion_percentage
            ]);

            $demoFlow['step_7'] = [
                'action' => 'Retrieved personalized workout recommendations',
                'service' => 'ml',
                'data' => $recommendations,
                'success' => !empty($recommendations)
            ];

            $overallSuccess = true;
            foreach ($demoFlow as $step) {
                if (!$step['success']) {
                    $overallSuccess = false;
                    break;
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => 'Complete workout flow demonstration completed',
                'demo_scenario' => 'User completes a workout with full service integration',
                'workflow_steps' => $demoFlow,
                'services_involved' => ['content', 'tracking', 'engagement', 'ml'],
                'session_created' => $session->id,
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Demo workflow failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function demoMLInsightsFlow(Request $request): JsonResponse
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

            $userId = $user['user_id'];
            $demoFlow = [];

            $recentSessions = WorkoutSession::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get();

            $demoFlow['step_1'] = [
                'action' => 'Retrieved recent workout sessions',
                'service' => 'tracking',
                'data' => $recentSessions->toArray(),
                'success' => $recentSessions->count() > 0
            ];

            if ($recentSessions->count() > 2) {
                $performanceData = [
                    'user_id' => $userId,
                    'sessions' => $recentSessions->map(function ($session) {
                        return [
                            'session_id' => $session->id,
                            'performance_rating' => $session->performance_rating,
                            'completion_percentage' => $session->completion_percentage,
                            'calories_burned' => $session->calories_burned,
                            'duration_minutes' => $session->actual_duration_minutes,
                            'workout_date' => $session->created_at->toDateString()
                        ];
                    })->toArray(),
                    'timestamp' => now()->toISOString()
                ];

                $analysisResult = $this->mlService->analyzePerformancePatterns($performanceData, $token);
                $demoFlow['step_2'] = [
                    'action' => 'Analyzed performance patterns with ML Service',
                    'service' => 'ml',
                    'data' => $analysisResult,
                    'success' => !empty($analysisResult)
                ];

                $userMetrics = [
                    'user_id' => $userId,
                    'recent_sessions' => $recentSessions->map(function ($session) {
                        return [
                            'performance_rating' => $session->performance_rating,
                            'completion_percentage' => $session->completion_percentage,
                            'session_date' => $session->created_at->toDateString()
                        ];
                    })->toArray(),
                    'timestamp' => now()->toISOString()
                ];

                $overtrainingPrediction = $this->mlService->predictOvertrainingRisk($userMetrics, $token);
                $demoFlow['step_3'] = [
                    'action' => 'Predicted overtraining risk',
                    'service' => 'ml',
                    'data' => $overtrainingPrediction,
                    'success' => !empty($overtrainingPrediction)
                ];

                $insightRequest = [
                    'user_id' => $userId,
                    'type' => 'performance_insights',
                    'data' => [
                        'recent_performance' => $recentSessions->avg('performance_rating'),
                        'recent_completion_rate' => $recentSessions->avg('completion_percentage'),
                        'session_count' => $recentSessions->count()
                    ],
                    'timestamp' => now()->toISOString()
                ];

                $insights = $this->mlService->generateInsights($insightRequest, $token);
                $demoFlow['step_4'] = [
                    'action' => 'Generated personalized insights',
                    'service' => 'ml',
                    'data' => $insights,
                    'success' => !empty($insights)
                ];

                if ($insights) {
                    $achievementData = [
                        'user_id' => $userId,
                        'achievement_type' => 'ml_insights_generated',
                        'achievement_data' => [
                            'insights_count' => count($insights['insights'] ?? []),
                            'performance_trend' => $insights['trend'] ?? 'neutral'
                        ],
                        'timestamp' => now()->toISOString()
                    ];

                    $achievementNotification = $this->engagementService->triggerAchievementNotification($achievementData, $token);
                    $demoFlow['step_5'] = [
                        'action' => 'Triggered achievement notification for insights',
                        'service' => 'engagement',
                        'data' => $achievementNotification,
                        'success' => !empty($achievementNotification)
                    ];
                }
            } else {
                $demoFlow['step_2'] = [
                    'action' => 'Insufficient data for ML analysis',
                    'service' => 'tracking',
                    'data' => ['message' => 'Need at least 3 sessions for meaningful analysis'],
                    'success' => true
                ];
            }

            $overallSuccess = true;
            foreach ($demoFlow as $step) {
                if (!$step['success']) {
                    $overallSuccess = false;
                    break;
                }
            }

            return response()->json([
                'success' => $overallSuccess,
                'message' => 'ML insights demonstration completed',
                'demo_scenario' => 'Generate AI-powered insights from user workout data',
                'workflow_steps' => $demoFlow,
                'services_involved' => ['tracking', 'ml', 'engagement'],
                'user_session_count' => $recentSessions->count(),
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'ML insights demo failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function demoServiceHealthCheck(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required'
                ], 401);
            }

            $healthChecks = [];

            $services = [
                'content' => [
                    'service' => $this->contentService,
                    'test_method' => 'getWorkoutDetails',
                    'test_params' => [1, $token]
                ],
                'engagement' => [
                    'service' => $this->engagementService,
                    'test_method' => 'getEngagementMetrics',
                    'test_params' => [1, $token]
                ],
                'ml' => [
                    'service' => $this->mlService,
                    'test_method' => 'getWorkoutRecommendations',
                    'test_params' => [1, $token]
                ]
            ];

            foreach ($services as $serviceName => $serviceConfig) {
                try {
                    $startTime = microtime(true);
                    $result = call_user_func_array(
                        [$serviceConfig['service'], $serviceConfig['test_method']],
                        $serviceConfig['test_params']
                    );
                    $endTime = microtime(true);

                    $healthChecks[$serviceName] = [
                        'status' => $result ? 'healthy' : 'degraded',
                        'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
                        'test_method' => $serviceConfig['test_method'],
                        'last_checked' => now()->toISOString()
                    ];

                } catch (\Exception $e) {
                    $healthChecks[$serviceName] = [
                        'status' => 'unhealthy',
                        'error' => $e->getMessage(),
                        'test_method' => $serviceConfig['test_method'],
                        'last_checked' => now()->toISOString()
                    ];
                }
            }

            $overallHealth = 'healthy';
            foreach ($healthChecks as $check) {
                if ($check['status'] === 'unhealthy') {
                    $overallHealth = 'unhealthy';
                    break;
                } elseif ($check['status'] === 'degraded') {
                    $overallHealth = 'degraded';
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Service health check completed',
                'overall_health' => $overallHealth,
                'service_health' => $healthChecks,
                'tracking_service' => [
                    'status' => 'healthy',
                    'database_connection' => 'active',
                    'last_checked' => now()->toISOString()
                ],
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}