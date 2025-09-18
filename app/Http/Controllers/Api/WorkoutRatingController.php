<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkoutRating;
use App\Models\UserExerciseHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class WorkoutRatingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'workout_id' => 'required|integer',
                'session_id' => 'required|integer',
                'rating_value' => 'required|numeric|between:1,5',
                'difficulty_rating' => 'nullable|in:too_easy,just_right,too_hard',
                'enjoyment_rating' => 'nullable|numeric|between:1,5',
                'would_recommend' => 'nullable|boolean',
                'feedback_comment' => 'nullable|string|max:1000'
            ]);

            $rating = WorkoutRating::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Workout rating recorded successfully',
                'data' => $rating
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record workout rating',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserRatings($userId): JsonResponse
    {
        try {
            $ratings = WorkoutRating::forUser($userId)
                ->with('workoutSession')
                ->latestFirst()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'User workout ratings retrieved successfully',
                'data' => $ratings
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve workout ratings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeExerciseFeedback(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'exercise_id' => 'required|integer',
                'session_id' => 'required|integer',
                'completed_repetitions' => 'nullable|integer|min:0',
                'completed_duration_seconds' => 'nullable|integer|min:0',
                'performance_score' => 'nullable|numeric|between:0,10',
                'difficulty_perceived' => 'nullable|in:very_easy,easy,moderate,hard,very_hard',
                'form_rating' => 'nullable|numeric|between:1,5',
                'fatigue_level' => 'nullable|in:none,low,moderate,high,extreme'
            ]);

            $exerciseHistory = UserExerciseHistory::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Exercise feedback recorded successfully',
                'data' => $exerciseHistory
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record exercise feedback',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getRatingStats($userId): JsonResponse
    {
        try {
            $stats = [
                'total_ratings' => WorkoutRating::forUser($userId)->count(),
                'average_rating' => WorkoutRating::forUser($userId)->avg('rating_value'),
                'average_enjoyment' => WorkoutRating::forUser($userId)->avg('enjoyment_rating'),
                'recommended_count' => WorkoutRating::forUser($userId)->recommended()->count(),
                'difficulty_distribution' => [
                    'too_easy' => WorkoutRating::forUser($userId)->where('difficulty_rating', 'too_easy')->count(),
                    'just_right' => WorkoutRating::forUser($userId)->where('difficulty_rating', 'just_right')->count(),
                    'too_hard' => WorkoutRating::forUser($userId)->where('difficulty_rating', 'too_hard')->count()
                ],
                'high_rated_workouts' => WorkoutRating::forUser($userId)->highRated()->count(),
                'low_rated_workouts' => WorkoutRating::forUser($userId)->lowRated()->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Rating statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve rating statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}