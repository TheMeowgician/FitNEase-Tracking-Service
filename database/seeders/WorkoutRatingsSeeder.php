<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class WorkoutRatingsSeeder extends Seeder
{
    /**
     * Seed workout ratings for ML collaborative filtering.
     *
     * This imports the exact rating data used to train:
     * - Collaborative Filtering Model
     * - Hybrid Recommendation Model
     */
    public function run(): void
    {
        // Path to the CSV file used in ML training
        $csvPath = storage_path('app/ml_data/workout_ratings.csv');

        // Check if file exists
        if (!File::exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");
            $this->command->info("Please copy workout_ratings.csv to storage/app/ml_data/");
            return;
        }

        $this->command->info("🔄 Loading workout ratings from ML training dataset...");

        // Clear existing ratings
        DB::table('workout_ratings')->truncate();

        // Read and parse CSV
        $csv = array_map('str_getcsv', file($csvPath));
        $header = array_shift($csv); // Remove header row

        $ratingCount = 0;
        $batchSize = 100;
        $ratings = [];

        foreach ($csv as $row) {
            $data = array_combine($header, $row);

            $ratings[] = [
                'user_id' => (int) $data['user_id'],
                'exercise_id' => (int) $data['exercise_id'],
                'workout_id' => isset($data['workout_id']) ? (int) $data['workout_id'] : null,
                'rating' => (int) $data['rating'],
                'rating_date' => $data['rating_date'],
                'session_id' => isset($data['session_id']) ? (int) $data['session_id'] : null,
                'completion_status' => isset($data['completion_status']) ?
                    ($data['completion_status'] === 'True' || $data['completion_status'] === '1') : null,
                'difficulty_feedback' => isset($data['difficulty_feedback']) ?
                    (int) $data['difficulty_feedback'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $ratingCount++;

            // Insert in batches for performance
            if (count($ratings) >= $batchSize) {
                DB::table('workout_ratings')->insert($ratings);
                $ratings = [];
                $this->command->info("✅ Inserted {$ratingCount} ratings...");
            }
        }

        // Insert remaining ratings
        if (!empty($ratings)) {
            DB::table('workout_ratings')->insert($ratings);
        }

        $this->command->info("🎉 Successfully imported {$ratingCount} workout ratings from ML training dataset!");
        $this->command->info("📊 This data enables collaborative filtering recommendations");
        $this->command->info("🤖 ML models can now predict user preferences!");
    }
}