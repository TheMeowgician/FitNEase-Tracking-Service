<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WorkoutSessionController;
use App\Http\Controllers\Api\WorkoutRatingController;
use App\Http\Controllers\Api\ExerciseRatingController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\BMIController;
use App\Http\Controllers\Api\MLDataController;
use App\Http\Controllers\Api\RecommendationTrackingController;
use App\Http\Controllers\Api\ProgressionController;

Route::get('/user', function (Request $request) {
    return $request->attributes->get('user');
})->middleware('auth.api');

// Workout Session Management Routes
Route::prefix('')->middleware('auth.api')->group(function () {

    // Session Management
    Route::post('/workout-session', [WorkoutSessionController::class, 'store']);
    Route::put('/workout-session/{sessionId}', [WorkoutSessionController::class, 'update']);
    Route::get('/workout-sessions/{userId}', [WorkoutSessionController::class, 'getUserSessions']);
    Route::delete('/workout-session/{sessionId}', [WorkoutSessionController::class, 'destroy']);

    // Session Analytics
    Route::get('/session-stats/{userId}', [WorkoutSessionController::class, 'getSessionStats']);
    Route::get('/sessions-by-date/{userId}', [WorkoutSessionController::class, 'getSessionsByDateRange']);
    Route::get('/overtraining-risk/{userId}', [WorkoutSessionController::class, 'checkOvertrainingRisk']);
    Route::get('/group-stats/{groupId}', [WorkoutSessionController::class, 'getGroupStats']);

    // Workout Rating & Feedback
    Route::post('/workout-rating', [WorkoutRatingController::class, 'store']);
    Route::get('/workout-ratings/{userId}', [WorkoutRatingController::class, 'getUserRatings']);
    Route::post('/exercise-feedback', [WorkoutRatingController::class, 'storeExerciseFeedback']);
    Route::get('/rating-stats/{userId}', [WorkoutRatingController::class, 'getRatingStats']);

    // Exercise Ratings (for ML collaborative filtering)
    Route::post('/exercise-rating', [ExerciseRatingController::class, 'store']);
    Route::post('/exercise-ratings/batch', [ExerciseRatingController::class, 'storeBatch']);
    Route::get('/exercise-ratings/{userId}', [ExerciseRatingController::class, 'getUserRatings']);
    Route::get('/exercise-ratings/exercise/{exerciseId}', [ExerciseRatingController::class, 'getExerciseRatings']);
    Route::get('/exercise-ratings/stats/{userId}', [ExerciseRatingController::class, 'getRatingStats']);
    Route::get('/exercise-ratings/session/{sessionId}', [ExerciseRatingController::class, 'getSessionRatings']);

    // Progress Analytics
    Route::get('/user-history/{userId}', [ProgressController::class, 'getUserHistory']);
    Route::get('/progress/{userId}', [ProgressController::class, 'getProgress']);
    Route::get('/weekly-summary/{userId}', [ProgressController::class, 'getWeeklySummary']);
    Route::post('/generate-weekly-summary/{userId}', [ProgressController::class, 'generateWeeklySummary']);

    // BMI/Health Metrics
    Route::post('/bmi-record', [BMIController::class, 'store']);
    Route::get('/bmi-records/{userId}', [BMIController::class, 'getUserRecords']);
    Route::get('/latest-bmi/{userId}', [BMIController::class, 'getLatestRecord']);

    // Data for ML Service
    Route::get('/all-user-data', [MLDataController::class, 'getAllUserData']);
    Route::get('/user-patterns/{userId}', [MLDataController::class, 'getUserPatterns']);
    Route::post('/behavioral-data', [MLDataController::class, 'sendBehavioralData']);

    // ML Data - Exercise Ratings (CRITICAL for collaborative filtering)
    Route::get('/ml-data/exercise-ratings', [MLDataController::class, 'getExerciseRatings']);
    Route::get('/ml-data/user-ratings/{userId}', [MLDataController::class, 'getUserExerciseRatings']);
    Route::get('/ml-data/rating-stats', [MLDataController::class, 'getRatingStatistics']);

    // Recommendation Tracking
    Route::post('/recommendations/shown', [RecommendationTrackingController::class, 'trackRecommendationsShown']);
    Route::post('/recommendations/click', [RecommendationTrackingController::class, 'trackRecommendationClick']);
    Route::post('/recommendations/completion', [RecommendationTrackingController::class, 'trackRecommendationCompletion']);
    Route::post('/recommendations/rating', [RecommendationTrackingController::class, 'trackRecommendationRating']);
    Route::get('/recommendations/analytics/user/{userId}', [RecommendationTrackingController::class, 'getUserRecommendationAnalytics']);
    Route::get('/recommendations/analytics/session/{sessionId}', [RecommendationTrackingController::class, 'getSessionAnalytics']);
    Route::get('/recommendations/analytics/overall', [RecommendationTrackingController::class, 'getOverallMetrics']);

    // User Progression System
    Route::get('/progression/check/{userId}', [ProgressionController::class, 'checkEligibility']);
    Route::get('/progression/progress/{userId}', [ProgressionController::class, 'getProgress']);
    Route::post('/progression/promote', [ProgressionController::class, 'promoteUser']);
});

// Health check endpoint
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'service' => 'fitnease-tracking',
        'timestamp' => now()->toISOString(),
        'database' => 'connected'
    ]);
});

// ML Internal Endpoints - For ML service internal calls (no auth required)
Route::prefix('ml-internal')->group(function () {
    Route::get('/ml-data/exercise-ratings', [MLDataController::class, 'getExerciseRatings']);
    Route::get('/ml-data/user-ratings/{userId}', [MLDataController::class, 'getUserExerciseRatings']);
    Route::get('/ml-data/rating-stats', [MLDataController::class, 'getRatingStatistics']);
});

// Internal Endpoints - For service-to-service calls (no user auth required)
Route::prefix('internal')->group(function () {
    Route::get('/group-stats/{groupId}', [WorkoutSessionController::class, 'getGroupStats']);
    Route::get('/users/{userId}/stats', [WorkoutSessionController::class, 'getSessionStats']);
});

// Service Communication Testing Routes
Route::prefix('service-tests')->middleware('auth.api')->group(function () {
    Route::get('/content', [App\Http\Controllers\Api\ServiceTestController::class, 'testContentService']);
    Route::get('/engagement', [App\Http\Controllers\Api\ServiceTestController::class, 'testEngagementService']);
    Route::get('/ml', [App\Http\Controllers\Api\ServiceTestController::class, 'testMLService']);
    Route::get('/all', [App\Http\Controllers\Api\ServiceTestController::class, 'testAllServices']);
});

// Service Communication Testing Routes
Route::prefix('communication-tests')->middleware('auth.api')->group(function () {
    Route::get('/connectivity', [App\Http\Controllers\Api\ServiceCommunicationTestController::class, 'testServiceConnectivity']);
    Route::get('/auth-validation', [App\Http\Controllers\Api\ServiceCommunicationTestController::class, 'testAuthTokenValidation']);
    Route::post('/data-flow', [App\Http\Controllers\Api\ServiceCommunicationTestController::class, 'testDataFlow']);
    Route::post('/end-to-end', [App\Http\Controllers\Api\ServiceCommunicationTestController::class, 'testEndToEndWorkflow']);
});

// Service Integration Demonstration Routes
Route::prefix('integration-demo')->middleware('auth.api')->group(function () {
    Route::post('/complete-workout', [App\Http\Controllers\Api\ServiceIntegrationDemoController::class, 'demoCompleteWorkoutFlow']);
    Route::get('/ml-insights', [App\Http\Controllers\Api\ServiceIntegrationDemoController::class, 'demoMLInsightsFlow']);
    Route::get('/health-check', [App\Http\Controllers\Api\ServiceIntegrationDemoController::class, 'demoServiceHealthCheck']);
});

// Root health check for Docker
Route::get('/', function () {
    return response()->json([
        'service' => 'FitNEase Tracking Service',
        'status' => 'healthy',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString()
    ]);
});