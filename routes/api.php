<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WorkoutSessionController;
use App\Http\Controllers\Api\WorkoutRatingController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\BMIController;
use App\Http\Controllers\Api\MLDataController;

Route::get('/user', function (Request $request) {
    return $request->attributes->get('user');
})->middleware('auth.api');

// Workout Session Management Routes
Route::prefix('tracking')->middleware('auth.api')->group(function () {

    // Session Management
    Route::post('/workout-session', [WorkoutSessionController::class, 'store']);
    Route::put('/workout-session/{sessionId}', [WorkoutSessionController::class, 'update']);
    Route::get('/workout-sessions/{userId}', [WorkoutSessionController::class, 'getUserSessions']);
    Route::delete('/workout-session/{sessionId}', [WorkoutSessionController::class, 'destroy']);

    // Session Analytics
    Route::get('/session-stats/{userId}', [WorkoutSessionController::class, 'getSessionStats']);
    Route::get('/sessions-by-date/{userId}', [WorkoutSessionController::class, 'getSessionsByDateRange']);
    Route::get('/overtraining-risk/{userId}', [WorkoutSessionController::class, 'checkOvertrainingRisk']);

    // Rating & Feedback
    Route::post('/workout-rating', [WorkoutRatingController::class, 'store']);
    Route::get('/workout-ratings/{userId}', [WorkoutRatingController::class, 'getUserRatings']);
    Route::post('/exercise-feedback', [WorkoutRatingController::class, 'storeExerciseFeedback']);
    Route::get('/rating-stats/{userId}', [WorkoutRatingController::class, 'getRatingStats']);

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