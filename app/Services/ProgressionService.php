<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ProgressionService
{
    private $authServiceUrl;

    public function __construct()
    {
        $this->authServiceUrl = env('AUTH_SERVICE_URL', 'http://fitnease-auth');
    }

    /**
     * Check if user is eligible for promotion to next fitness level
     *
     * @param int $userId
     * @return array ['eligible' => bool, 'newLevel' => string|null, 'score' => float, 'requirements' => array]
     */
    public function checkPromotionEligibility(int $userId): array
    {
        try {
            // Get user data from auth service using internal endpoint
            $userResponse = Http::get("{$this->authServiceUrl}/api/internal/users/{$userId}");

            if (!$userResponse->successful()) {
                Log::error("Failed to fetch user data for progression check", [
                    'user_id' => $userId,
                    'status' => $userResponse->status(),
                    'body' => $userResponse->body()
                ]);
                return ['eligible' => false, 'newLevel' => null, 'score' => 0, 'requirements' => []];
            }

            // Internal endpoint returns user directly, not wrapped in 'data'
            $user = $userResponse->json();

            if (!$user || !isset($user['user_id'])) {
                Log::error("Invalid user data received", [
                    'user_id' => $userId,
                    'response' => $user
                ]);
                return ['eligible' => false, 'newLevel' => null, 'score' => 0, 'requirements' => []];
            }

            $currentLevel = $user['fitness_level'] ?? 'beginner';

            // Use active_days instead of days since registration
            $activeDays = $user['active_days'] ?? 0;

            // Determine target level
            $targetLevel = $this->getNextLevel($currentLevel);

            if (!$targetLevel) {
                // Already at max level - but still show their workout stats
                $completedWorkouts = $user['total_workouts_completed'] ?? 0;
                $workoutMinutes = $user['total_workout_minutes'] ?? 0;
                $activeDays = $user['active_days'] ?? 0;
                $weeksActive = min(ceil($activeDays / 7), 52);
                $longestStreak = $user['longest_streak_days'] ?? 0;
                $groupWorkouts = $user['group_workouts_count'] ?? 0;
                $goalsAchieved = $user['goals_achieved_count'] ?? 0;

                return [
                    'eligible' => false,
                    'newLevel' => null,
                    'score' => 0,
                    'threshold' => 0,
                    'message' => 'Congratulations! You have reached the maximum fitness level!',
                    'requirements' => [
                        'min_days' => 0,
                        'current_days' => $activeDays,
                        'meets_time_requirement' => true,
                        'meets_score_requirement' => true,
                        'completed_workouts' => $completedWorkouts,
                        'workout_minutes' => $workoutMinutes,
                        'weeks_active' => $weeksActive,
                        'longest_streak' => $longestStreak,
                        'group_workouts' => $groupWorkouts,
                        'goals_achieved' => $goalsAchieved,
                    ],
                    'breakdown' => [
                        'workouts_points' => $completedWorkouts,
                        'minutes_points' => $workoutMinutes,
                        'completion_rate_points' => 100,
                        'weeks_active_points' => $weeksActive,
                        'streak_points' => $longestStreak,
                        'group_workouts_points' => $groupWorkouts,
                        'goals_achieved_points' => $goalsAchieved,
                    ],
                ];
            }

            // Calculate eligibility based on target level
            if ($targetLevel === 'intermediate') {
                return $this->checkIntermediateEligibility($user, $activeDays);
            } elseif ($targetLevel === 'advanced') {
                return $this->checkAdvancedEligibility($user, $activeDays);
            }

            return ['eligible' => false, 'newLevel' => null, 'score' => 0, 'requirements' => []];

        } catch (\Exception $e) {
            Log::error("Error checking promotion eligibility", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return ['eligible' => false, 'newLevel' => null, 'score' => 0, 'requirements' => []];
        }
    }

    /**
     * Check eligibility for INTERMEDIATE level
     */
    private function checkIntermediateEligibility(array $user, int $activeDays): array
    {
        $minDays = 28; // 4 weeks of active login days

        // Extract metrics
        $completedWorkouts = $user['total_workouts_completed'] ?? 0;
        $workoutMinutes = $user['total_workout_minutes'] ?? 0;
        $profileCompleteness = $user['profile_completeness_percentage'] ?? 0;

        // Calculate weeks active based on active days (not registration days)
        $weeksActive = min(ceil($activeDays / 7), 12);

        // Calculate completion rate (assuming 90% if no data)
        $completionRate = $completedWorkouts > 0 ? 0.88 : 0; // Default 88% from doc example

        // FORMULA FOR INTERMEDIATE PROMOTION:
        // Score = (Completed_Workouts × 2) +
        //         (Workout_Minutes ÷ 10) +
        //         (Completion_Rate × 100) +
        //         (Weeks_Active × 5) +
        //         (Profile_Completeness × 50)
        $score = ($completedWorkouts * 2) +
                 ($workoutMinutes / 10) +
                 ($completionRate * 100) +
                 ($weeksActive * 5) +
                 (($profileCompleteness / 100) * 50);

        $threshold = 300;
        $meetsTimeRequirement = $activeDays >= $minDays;
        $meetsScoreRequirement = $score >= $threshold;

        $eligible = $meetsTimeRequirement && $meetsScoreRequirement;

        return [
            'eligible' => $eligible,
            'newLevel' => 'intermediate', // Always show target level, not just when eligible
            'score' => round($score, 2),
            'threshold' => $threshold,
            'requirements' => [
                'min_days' => $minDays,
                'current_days' => $activeDays,
                'meets_time_requirement' => $meetsTimeRequirement,
                'meets_score_requirement' => $meetsScoreRequirement,
                'completed_workouts' => $completedWorkouts,
                'workout_minutes' => $workoutMinutes,
                'profile_completeness' => $profileCompleteness,
                'weeks_active' => $weeksActive,
            ],
            'breakdown' => [
                'workouts_points' => $completedWorkouts * 2,
                'minutes_points' => round($workoutMinutes / 10, 2),
                'completion_rate_points' => round($completionRate * 100, 2),
                'weeks_active_points' => $weeksActive * 5,
                'profile_points' => round(($profileCompleteness / 100) * 50, 2),
            ],
            'message' => $eligible
                ? 'Congratulations! You are eligible for promotion to Intermediate level!'
                : 'Keep working out to reach Intermediate level. You\'re ' . round(($score/$threshold)*100, 1) . '% there!'
        ];
    }

    /**
     * Check eligibility for ADVANCED level
     */
    private function checkAdvancedEligibility(array $user, int $activeDays): array
    {
        $minDays = 112; // 16 weeks of active login days
        $levelUpdatedAt = $user['fitness_level_updated_at'] ? Carbon::parse($user['fitness_level_updated_at']) : null;
        $daysAtIntermediate = $levelUpdatedAt ? $levelUpdatedAt->diffInDays(now()) : 0;
        $minDaysAtIntermediate = 84; // 12 weeks

        // Extract metrics
        $completedWorkouts = $user['total_workouts_completed'] ?? 0;
        $workoutMinutes = $user['total_workout_minutes'] ?? 0;
        $advancedWorkouts = $user['advanced_workouts_completed'] ?? 0;
        $goalsAchieved = $user['goals_achieved_count'] ?? 0;
        $groupWorkouts = $user['group_workouts_count'] ?? 0;
        $longestStreak = $user['longest_streak_days'] ?? 0;

        // Calculate weeks active based on active days
        $weeksActive = min(ceil($activeDays / 7), 24);

        // Calculate completion rate (assuming 92% for advanced users)
        $completionRate = $completedWorkouts > 0 ? 0.92 : 0;

        // FORMULA FOR ADVANCED PROMOTION:
        // Score = (Completed_Workouts × 1.5) +
        //         (Workout_Minutes ÷ 8) +
        //         (Completion_Rate × 150) +
        //         (Weeks_Active × 8) +
        //         (Advanced_Workouts_Completed × 5) +
        //         (Goals_Achieved × 100) +
        //         (Group_Workouts_Participated × 10) +
        //         (Longest_Streak_Days × 3)
        $score = ($completedWorkouts * 1.5) +
                 ($workoutMinutes / 8) +
                 ($completionRate * 150) +
                 ($weeksActive * 8) +
                 ($advancedWorkouts * 5) +
                 ($goalsAchieved * 100) +
                 ($groupWorkouts * 10) +
                 ($longestStreak * 3);

        $threshold = 1000;
        $meetsTimeRequirement = $activeDays >= $minDays;
        $meetsIntermediateDuration = $daysAtIntermediate >= $minDaysAtIntermediate;
        $meetsScoreRequirement = $score >= $threshold;

        $eligible = $meetsTimeRequirement && $meetsIntermediateDuration && $meetsScoreRequirement;

        return [
            'eligible' => $eligible,
            'newLevel' => 'advanced', // Always show target level, not just when eligible
            'score' => round($score, 2),
            'threshold' => $threshold,
            'requirements' => [
                'min_days' => $minDays,
                'current_days' => $activeDays,
                'min_days_at_intermediate' => $minDaysAtIntermediate,
                'current_days_at_intermediate' => $daysAtIntermediate,
                'meets_time_requirement' => $meetsTimeRequirement,
                'meets_intermediate_duration' => $meetsIntermediateDuration,
                'meets_score_requirement' => $meetsScoreRequirement,
                'completed_workouts' => $completedWorkouts,
                'workout_minutes' => $workoutMinutes,
                'advanced_workouts' => $advancedWorkouts,
                'goals_achieved' => $goalsAchieved,
                'group_workouts' => $groupWorkouts,
                'longest_streak' => $longestStreak,
                'weeks_active' => $weeksActive,
            ],
            'breakdown' => [
                'workouts_points' => round($completedWorkouts * 1.5, 2),
                'minutes_points' => round($workoutMinutes / 8, 2),
                'completion_rate_points' => round($completionRate * 150, 2),
                'weeks_active_points' => $weeksActive * 8,
                'advanced_workouts_points' => $advancedWorkouts * 5,
                'goals_achieved_points' => $goalsAchieved * 100,
                'group_workouts_points' => $groupWorkouts * 10,
                'streak_points' => $longestStreak * 3,
            ],
            'message' => $eligible
                ? 'Congratulations! You are eligible for promotion to Advanced level!'
                : 'Keep pushing yourself to reach Advanced level. You\'re ' . round(($score/$threshold)*100, 1) . '% there!'
        ];
    }

    /**
     * Promote user to next fitness level
     */
    public function promoteUser(int $userId, string $newLevel): bool
    {
        try {
            $response = Http::put("{$this->authServiceUrl}/api/internal/users/{$userId}/fitness-level", [
                'fitness_level' => $newLevel,
                'fitness_level_updated_at' => now()->toDateTimeString()
            ]);

            if ($response->successful()) {
                Log::info("User promoted to {$newLevel}", ['user_id' => $userId]);

                // TODO: Trigger notification via fitneasecomms
                // TODO: Trigger achievement via fitneaseengagement

                return true;
            }

            Log::error("Failed to promote user", [
                'user_id' => $userId,
                'new_level' => $newLevel,
                'status' => $response->status()
            ]);
            return false;

        } catch (\Exception $e) {
            Log::error("Error promoting user", [
                'user_id' => $userId,
                'new_level' => $newLevel,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update user progression metrics after workout completion
     */
    public function updateProgressionMetrics(int $userId, array $workoutData): void
    {
        try {
            $updates = [];

            // Increment total workouts
            if (isset($workoutData['completed']) && $workoutData['completed']) {
                $updates['increment_workouts'] = true;
            }

            // Add workout minutes
            if (isset($workoutData['duration_minutes'])) {
                $updates['add_minutes'] = $workoutData['duration_minutes'];
            }

            // Track advanced workouts (difficulty 3+)
            if (isset($workoutData['difficulty']) && $workoutData['difficulty'] >= 3) {
                $updates['increment_advanced_workouts'] = true;
            }

            // Track group workouts
            if (isset($workoutData['is_group_workout']) && $workoutData['is_group_workout']) {
                $updates['increment_group_workouts'] = true;
            }

            // Update last workout date
            $updates['last_workout_date'] = date('Y-m-d');

            // Send update to auth service
            $response = Http::put("{$this->authServiceUrl}/api/internal/users/{$userId}/progression-metrics", $updates);

            if (!$response->successful()) {
                Log::error("Failed to update progression metrics", [
                    'user_id' => $userId,
                    'updates' => $updates
                ]);
            }

        } catch (\Exception $e) {
            Log::error("Error updating progression metrics", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate and update streak
     */
    public function updateStreak(int $userId, string $lastWorkoutDate, string $currentWorkoutDate): void
    {
        try {
            $last = Carbon::parse($lastWorkoutDate);
            $current = Carbon::parse($currentWorkoutDate);
            $daysDiff = $last->diffInDays($current);

            $updates = [];

            if ($daysDiff === 1) {
                // Consecutive day - increment streak
                $updates['increment_streak'] = true;
            } elseif ($daysDiff > 1) {
                // Streak broken - reset to 1
                $updates['reset_streak'] = true;
            }
            // If same day, no update needed

            if (!empty($updates)) {
                Http::put("{$this->authServiceUrl}/api/internal/users/{$userId}/streak", $updates);
            }

        } catch (\Exception $e) {
            Log::error("Error updating streak", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get next level based on current level
     */
    private function getNextLevel(string $currentLevel): ?string
    {
        $levels = [
            'beginner' => 'intermediate',
            'intermediate' => 'advanced',
            'advanced' => null, // Max level
        ];

        return $levels[$currentLevel] ?? null;
    }

    /**
     * Calculate profile completeness percentage
     */
    public function calculateProfileCompleteness(array $user): int
    {
        $requiredFields = [
            'fitness_goals',
            'target_muscle_groups',
            'activity_level',
            'workout_experience_years',
            'available_equipment',
            'time_constraints_minutes',
        ];

        $completedFields = 0;
        foreach ($requiredFields as $field) {
            if (isset($user[$field]) && !empty($user[$field])) {
                $completedFields++;
            }
        }

        return round(($completedFields / count($requiredFields)) * 100);
    }
}
