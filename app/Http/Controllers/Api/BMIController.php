<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BMIRecord;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class BMIController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|integer',
                'weight_kg' => 'required|numeric|min:20|max:300',
                'height_cm' => 'required|numeric|min:100|max:250',
                'body_fat_percentage' => 'nullable|numeric|min:0|max:60',
                'muscle_mass_kg' => 'nullable|numeric|min:0|max:100'
            ]);

            $bmi = BMIRecord::calculateBMI($validated['weight_kg'], $validated['height_cm']);
            $category = BMIRecord::determineBMICategory($bmi);

            $validated['bmi_value'] = $bmi;
            $validated['bmi_category'] = $category;

            $record = BMIRecord::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'BMI record created successfully',
                'data' => $record
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
                'message' => 'Failed to create BMI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getUserRecords($userId): JsonResponse
    {
        try {
            $records = BMIRecord::forUser($userId)
                ->latestFirst()
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'BMI records retrieved successfully',
                'data' => $records
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve BMI records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getLatestRecord($userId): JsonResponse
    {
        try {
            $record = BMIRecord::forUser($userId)
                ->latestFirst()
                ->first();

            if (!$record) {
                return response()->json([
                    'success' => false,
                    'message' => 'No BMI records found for this user'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Latest BMI record retrieved successfully',
                'data' => $record
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve latest BMI record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}