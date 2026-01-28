<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProgressionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProgressionController extends Controller
{
    private ProgressionService $progressionService;

    public function __construct(ProgressionService $progressionService)
    {
        $this->progressionService = $progressionService;
    }

    /**
     * Check if user is eligible for promotion
     *
     * GET /api/tracking/progression/check/{userId}
     */
    public function checkEligibility(int $userId): JsonResponse
    {
        $result = $this->progressionService->checkPromotionEligibility($userId);

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Promote user to next fitness level
     *
     * POST /api/tracking/progression/promote
     * Body: { "user_id": 123 }
     */
    public function promoteUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer'
        ]);

        $userId = $validated['user_id'];

        // First check eligibility
        $eligibility = $this->progressionService->checkPromotionEligibility($userId);

        if (!$eligibility['eligible']) {
            return response()->json([
                'success' => false,
                'message' => 'User is not eligible for promotion',
                'eligibility' => $eligibility
            ], 400);
        }

        // Promote user
        $promoted = $this->progressionService->promoteUser($userId, $eligibility['newLevel']);

        if ($promoted) {
            return response()->json([
                'success' => true,
                'message' => "User promoted to {$eligibility['newLevel']} level!",
                'new_level' => $eligibility['newLevel']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to promote user'
        ], 500);
    }

    /**
     * Get progression progress for user
     *
     * GET /api/tracking/progression/progress/{userId}
     */
    public function getProgress(int $userId): JsonResponse
    {
        $eligibility = $this->progressionService->checkPromotionEligibility($userId);

        // Handle threshold safely
        $threshold = $eligibility['threshold'] ?? 0;
        $currentScore = $eligibility['score'] ?? 0;

        // Calculate score progress (0-100%)
        $scoreProgress = $threshold > 0
            ? min(round(($currentScore / $threshold) * 100, 1), 100)
            : 0;

        // Calculate time progress (0-100%)
        $requirements = $eligibility['requirements'] ?? [];
        $minDays = $requirements['min_days'] ?? 1;
        $currentDays = $requirements['current_days'] ?? 0;
        // For max-level users, minDays is 0 - treat as 100% progress
        $timeProgress = $minDays > 0
            ? min(round(($currentDays / $minDays) * 100, 1), 100)
            : 100;

        // Overall progress is the MINIMUM of both (both must be 100% to promote)
        $overallProgress = min($scoreProgress, $timeProgress);

        return response()->json([
            'success' => true,
            'data' => [
                'eligible_for_promotion' => $eligibility['eligible'],
                'current_score' => $currentScore,
                'required_score' => $threshold,
                'score_progress' => $scoreProgress,
                'time_progress' => $timeProgress,
                'progress_percentage' => $overallProgress,
                'next_level' => $eligibility['newLevel'] ?? 'Max level reached',
                'requirements' => $requirements,
                'breakdown' => $eligibility['breakdown'] ?? [],
                'message' => $eligibility['message'] ?? 'No progression data available'
            ]
        ]);
    }
}
