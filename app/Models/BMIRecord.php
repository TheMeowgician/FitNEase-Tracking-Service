<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class BMIRecord extends Model
{
    use HasFactory;

    protected $table = 'bmi_records';
    protected $primaryKey = 'bmi_record_id';

    protected $fillable = [
        'user_id',
        'weight_kg',
        'height_cm',
        'bmi_value',
        'bmi_category',
        'body_fat_percentage',
        'muscle_mass_kg',
        'recorded_at'
    ];

    protected $casts = [
        'weight_kg' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'bmi_value' => 'decimal:2',
        'body_fat_percentage' => 'decimal:2',
        'muscle_mass_kg' => 'decimal:2',
        'recorded_at' => 'datetime'
    ];

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderBy('recorded_at', 'desc');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('recorded_at', [$startDate, $endDate]);
    }

    public function getHeightInMetersAttribute()
    {
        return $this->height_cm / 100;
    }

    public function getFormattedWeightAttribute()
    {
        return $this->weight_kg . ' kg';
    }

    public function getFormattedHeightAttribute()
    {
        return $this->height_cm . ' cm';
    }

    public function getBmiStatusAttribute()
    {
        if (!$this->bmi_category) {
            return $this->calculateBmiCategory($this->bmi_value);
        }
        return ucfirst($this->bmi_category);
    }

    public function getBmiColorAttribute()
    {
        switch ($this->bmi_category) {
            case 'underweight':
                return '#3498db'; // Blue
            case 'normal':
                return '#2ecc71'; // Green
            case 'overweight':
                return '#f39c12'; // Orange
            case 'obese':
                return '#e74c3c'; // Red
            default:
                return '#95a5a6'; // Gray
        }
    }

    private function calculateBmiCategory($bmi)
    {
        if ($bmi < 18.5) return 'Underweight';
        if ($bmi < 25) return 'Normal';
        if ($bmi < 30) return 'Overweight';
        return 'Obese';
    }

    public static function calculateBMI($weightKg, $heightCm)
    {
        $heightM = $heightCm / 100;
        return round($weightKg / ($heightM * $heightM), 2);
    }

    public static function determineBMICategory($bmi)
    {
        if ($bmi < 18.5) return 'underweight';
        if ($bmi < 25) return 'normal';
        if ($bmi < 30) return 'overweight';
        return 'obese';
    }
}