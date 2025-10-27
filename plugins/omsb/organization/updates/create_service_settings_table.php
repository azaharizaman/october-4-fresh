<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateServiceSettingsTable Migration
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('omsb_organization_service_settings', function (Blueprint $table) {
            $table->id();
            $table->json('services'); // Store service configuration as JSON
            $table->timestamps();
        });

        // Insert default services data
        $this->seedDefaultServices();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('omsb_organization_service_settings');
    }

    /**
     * Seed default services
     */
    protected function seedDefaultServices()
    {
        $defaultServices = [
            'FMS' => [
                'code' => 'FMS',
                'name' => 'Facility Management Service',
                'description' => 'General facility management and maintenance',
                'is_active' => true,
                'color' => '#2563eb', // Blue
                'budget_prefix' => 'FMS',
                'approval_threshold' => 50000
            ],
            'FEMS' => [
                'code' => 'FEMS',
                'name' => 'Facility Engineering Management Service',
                'description' => 'Engineering and technical facility services',
                'is_active' => true,
                'color' => '#dc2626', // Red
                'budget_prefix' => 'FEMS',
                'approval_threshold' => 75000
            ],
            'BEMS' => [
                'code' => 'BEMS',
                'name' => 'Biomedical Equipment Maintenance Service',
                'description' => 'Medical equipment maintenance and calibration',
                'is_active' => true,
                'color' => '#16a34a', // Green
                'budget_prefix' => 'BEMS',
                'approval_threshold' => 100000
            ],
            'CLS' => [
                'code' => 'CLS',
                'name' => 'Cleaning Service',
                'description' => 'Cleaning and sanitation services',
                'is_active' => true,
                'color' => '#ea580c', // Orange
                'budget_prefix' => 'CLS',
                'approval_threshold' => 30000
            ],
            'HWMS' => [
                'code' => 'HWMS',
                'name' => 'Hospital Waste Management Service',
                'description' => 'Medical and general waste management',
                'is_active' => true,
                'color' => '#7c2d12', // Brown
                'budget_prefix' => 'HWMS',
                'approval_threshold' => 40000
            ],
            'LLS' => [
                'code' => 'LLS',
                'name' => 'Linen and Laundry Service',
                'description' => 'Laundry and linen management services',
                'is_active' => true,
                'color' => '#7c3aed', // Purple
                'budget_prefix' => 'LLS',
                'approval_threshold' => 35000
            ],
            'ADM' => [
                'code' => 'ADM',
                'name' => 'Administration',
                'description' => 'Administrative and general management',
                'is_active' => true,
                'color' => '#374151', // Gray
                'budget_prefix' => 'ADM',
                'approval_threshold' => 25000
            ]
        ];

        DB::table('omsb_organization_service_settings')->insert([
            'services' => json_encode($defaultServices),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
};