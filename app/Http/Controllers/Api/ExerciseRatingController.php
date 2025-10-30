<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutExerciseRating;
use App\Models\UserExerciseHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Exercise Rating Controller
 *
 * Handles individual exercise ratings (not workout ratings).
 * This is CRITICAL for collaborative filtering in the ML service.
 *
 * Mobile client calls these endpoints after workout completion
 * to rate each exercise individually.
 */
class ExerciseRatingController extends Controller
{
    /**
     * Store exercise ratings (batch)
     * Called after workout completion with array of exercise ratings
     *
     * POST /api/tracking/exercise-ratings/batch
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeBatch(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'session_id' => 'required|integer',
                'workout_id' => 'nullable|integer',
                'ratings' => 'required|array|min:1',
                'ratings.*.exercise_id' => 'required|integer',
                'ratings.*.rating_value' => 'required|numeric|between:1,5',
                'ratings.*.difficulty_perceived' => 'nullable|in:too_easy,appropriate,challenging,too_hard',
                'ratings.*.enjoyment_rating' => 'nullable|numeric|between:1,5',
                'ratings.*.would_do_again' => 'nullable|boolean',
                'ratings.*.notes' => 'nullable|string|max:500',
                'ratings.*.completed' => 'nullable|boolean',
                'ratings.*.completed_reps' => 'nullable|integer|min:0',
                'ratings.*.completed_duration_seconds' => 'nullable|integer|min:0',
                'ratings.*.came_from_recommendation' => 'nullable|boolean',
                'ratings.*.recommendation_session_id' => 'nullable|string',
            ]);

            $savedRatings = [];
            $errors = [];

            DB::beginTransaction();

            try {
                foreach ($validated['ratings'] as $index => $ratingData) {
                    try {
                        // Check if rating already exists for this user-exercise-session combination
                        $existingRating = WorkoutExerciseRating::where('user_id', $validated['user_id'])
                            ->where('exercise_id', $ratingData['exercise_id'])
                            ->where('session_id', $validated['session_id'])
                            ->first();

                        if ($existingRating) {
                            // Update existing rating
                            $existingRating->update([
                                'rating_value' => $ratingData['rating_value'],
                                'difficulty_perceived' => $ratingData['difficulty_perceived'] ?? null,
                                'enjoyment_rating' => $ratingData['enjoyment_rating'] ?? null,
                                'would_do_again' => $ratingData['would_do_again'] ?? true,
                                'notes' => $ratingData['notes'] ?? null,
                                'completed' => $ratingData['completed'] ?? true,
                                'completed_reps' => $ratingData['completed_reps'] ?? null,
                                'completed_duration_seconds' => $ratingData['completed_duration_seconds'] ?? null,
                                'rated_at' => now(),
                            ]);
                            $savedRatings[] = $existingRating;
                            Log::info("Updated exercise rating", [
                                'user_id' => $validated['user_id'],
                                'exercise_id' => $ratingData['exercise_id'],
                                'rating' => $ratingData['rating_value']
                            ]);
                        } else {
                            // Create new rating
                            $rating = WorkoutExerciseRating::create([
                                'user_id' => $validated['user_id'],
                                'exercise_id' => $ratingData['exercise_id'],
                                'workout_id' => $validated['workout_id'] ?? null,
                                'session_id' => $validated['session_id'],
                                'rating_value' => $ratingData['rating_value'],
                                'difficulty_perceived' => $ratingData['difficulty_perceived'] ?? null,
                                'enjoyment_rating' => $ratingData['enjoyment_rating'] ?? null,
                                'would_do_again' => $ratingData['would_do_again'] ?? true,
                                'notes' => $ratingData['notes'] ?? null,
                                'completed' => $ratingData['completed'] ?? true,
                                'completed_reps' => $ratingData['completed_reps'] ?? null,
                                'completed_duration_seconds' => $ratingData['completed_duration_seconds'] ?? null,
                                'came_from_recommendation' => $ratingData['came_from_recommendation'] ?? false,
                                'recommendation_session_id' => $ratingData['recommendation_session_id'] ?? null,
                                'rated_at' => now(),
                            ]);
                            $savedRatings[] = $rating;
                            Log::info("Created exercise rating", [
                                'user_id' => $validated['user_id'],
                                'exercise_id' => $ratingData['exercise_id'],
                                'rating' => $ratingData['rating_value']
                            ]);
                        }
                    } catch (\Exception $e) {
                        $errors[] = [
                            'index' => $index,
                            'exercise_id' => $ratingData['exercise_id'],
                            'error' => $e->getMessage()
                        ];
                        Log::error("Failed to save exercise rating", [
                            'exercise_id' => $ratingData['exercise_id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                // If all ratings failed, rollback
                if (count($savedRatings) === 0 && count($errors) > 0) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to save any ratings',
                        'errors' => $errors
                    ], 500);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Exercise ratings saved successfully',
                    'data' => [
                        'saved_count' => count($savedRatings),
                        'error_count' => count($errors),
                        'ratings' => $savedRatings,
                        'errors' => $errors
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save exercise ratings batch', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save exercise ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store single exercise rating
     *
     * POST /api/tracking/exercise-rating
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'exercise_id' => 'required|integer',
                'session_id' => 'required|integer',
                'workout_id' => 'nullable|integer',
                'rating_value' => 'required|numeric|between:1,5',
                'difficulty_perceived' => 'nullable|in:too_easy,appropriate,challenging,too_hard',
                'enjoyment_rating' => 'nullable|numeric|between:1,5',
                'would_do_again' => 'nullable|boolean',
                'notes' => 'nullable|string|max:500',
                'completed' => 'nullable|boolean',
                'completed_reps' => 'nullable|integer|min:0',
                'completed_duration_seconds' => 'nullable|integer|min:0',
                'came_from_recommendation' => 'nullable|boolean',
                'recommendation_session_id' => 'nullable|string',
            ]);

            // Check if rating already exists
            $existingRating = WorkoutExerciseRating::where('user_id', $validated['user_id'])
                ->where('exercise_id', $validated['exercise_id'])
                ->where('session_id', $validated['session_id'])
                ->first();

            if ($existingRating) {
                // Update existing rating
                $existingRating->update([
                    'rating_value' => $validated['rating_value'],
                    'difficulty_perceived' => $validated['difficulty_perceived'] ?? $existingRating->difficulty_perceived,
                    'enjoyment_rating' => $validated['enjoyment_rating'] ?? $existingRating->enjoyment_rating,
                    'would_do_again' => $validated['would_do_again'] ?? $existingRating->would_do_again,
                    'notes' => $validated['notes'] ?? $existingRating->notes,
                    'completed' => $validated['completed'] ?? $existingRating->completed,
                    'completed_reps' => $validated['completed_reps'] ?? $existingRating->completed_reps,
                    'completed_duration_seconds' => $validated['completed_duration_seconds'] ?? $existingRating->completed_duration_seconds,
                    'rated_at' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Exercise rating updated successfully',
                    'data' => $existingRating
                ], 200);
            }

            // Create new rating
            $rating = WorkoutExerciseRating::create(array_merge($validated, [
                'rated_at' => now()
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Exercise rating saved successfully',
                'data' => $rating
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to save exercise rating', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to save exercise rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's exercise ratings
     *
     * GET /api/tracking/exercise-ratings/{userId}
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getUserRatings($userId): JsonResponse
    {
        try {
            $ratings = WorkoutExerciseRating::forUser($userId)
                ->with('workoutSession')
                ->latestFirst()
                ->paginate(50);

            return response()->json([
                'success' => true,
                'message' => 'User exercise ratings retrieved successfully',
                'data' => $ratings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve user exercise ratings', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exercise ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ratings for a specific exercise
     *
     * GET /api/tracking/exercise-ratings/exercise/{exerciseId}
     *
     * @param int $exerciseId
     * @return JsonResponse
     */
    public function getExerciseRatings($exerciseId): JsonResponse
    {
        try {
            $ratings = WorkoutExerciseRating::forExercise($exerciseId)
                ->completed()
                ->latestFirst()
                ->get();

            $averageRating = WorkoutExerciseRating::getExerciseAverageRating($exerciseId);
            $totalRatings = $ratings->count();

            return response()->json([
                'success' => true,
                'message' => 'Exercise ratings retrieved successfully',
                'data' => [
                    'exercise_id' => $exerciseId,
                    'average_rating' => round($averageRating ?? 0, 2),
                    'total_ratings' => $totalRatings,
                    'ratings' => $ratings
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve exercise ratings', [
                'exercise_id' => $exerciseId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve exercise ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get rating statistics for a user
     *
     * GET /api/tracking/exercise-ratings/stats/{userId}
     *
     * @param int $userId
     * @return JsonResponse
     */
    public function getRatingStats($userId): JsonResponse
    {
        try {
            $stats = [
                'total_ratings' => WorkoutExerciseRating::forUser($userId)->count(),
                'average_rating' => WorkoutExerciseRating::forUser($userId)->avg('rating_value'),
                'average_enjoyment' => WorkoutExerciseRating::forUser($userId)->avg('enjoyment_rating'),
                'high_rated_exercises' => WorkoutExerciseRating::forUser($userId)->highRated(4.0)->count(),
                'low_rated_exercises' => WorkoutExerciseRating::forUser($userId)->lowRated(2.5)->count(),
                'completed_count' => WorkoutExerciseRating::forUser($userId)->completed()->count(),
                'incomplete_count' => WorkoutExerciseRating::forUser($userId)->incomplete()->count(),
                'difficulty_distribution' => [
                    'too_easy' => WorkoutExerciseRating::forUser($userId)->where('difficulty_perceived', 'too_easy')->count(),
                    'appropriate' => WorkoutExerciseRating::forUser($userId)->where('difficulty_perceived', 'appropriate')->count(),
                    'challenging' => WorkoutExerciseRating::forUser($userId)->where('difficulty_perceived', 'challenging')->count(),
                    'too_hard' => WorkoutExerciseRating::forUser($userId)->where('difficulty_perceived', 'too_hard')->count(),
                ],
                'recommendation_accuracy' => WorkoutExerciseRating::getRecommendationAccuracy($userId),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Rating statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve rating statistics', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rating statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ratings for a specific session
     *
     * GET /api/tracking/exercise-ratings/session/{sessionId}
     *
     * @param int $sessionId
     * @return JsonResponse
     */
    public function getSessionRatings($sessionId): JsonResponse
    {
        try {
            $ratings = WorkoutExerciseRating::forSession($sessionId)
                ->latestFirst()
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Session exercise ratings retrieved successfully',
                'data' => $ratings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve session ratings', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
