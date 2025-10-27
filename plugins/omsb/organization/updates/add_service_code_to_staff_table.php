<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * AddServiceCodeToStaffTable Migration
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('omsb_organization_staff', function (Blueprint $table) {
            $table->string('service_code', 10)->nullable()->after('company_id');
            $table->index('service_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('omsb_organization_staff', function (Blueprint $table) {
            $table->dropIndex(['service_code']);
            $table->dropColumn('service_code');
        });
    }
};