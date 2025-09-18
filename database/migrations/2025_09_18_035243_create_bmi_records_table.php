<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bmi_records', function (Blueprint $table) {
            $table->id('bmi_record_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('weight_kg', 5, 2);
            $table->decimal('height_cm', 5, 2);
            $table->decimal('bmi_value', 4, 2);
            $table->enum('bmi_category', ['underweight', 'normal', 'overweight', 'obese'])->nullable();
            $table->decimal('body_fat_percentage', 4, 2)->nullable();
            $table->decimal('muscle_mass_kg', 5, 2)->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'recorded_at'], 'idx_bmi_user_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bmi_records');
    }
};