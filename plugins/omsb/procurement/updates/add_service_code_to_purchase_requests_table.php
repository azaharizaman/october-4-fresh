<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * AddServiceCodeToPurchaseRequestsTable Migration
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('omsb_procurement_purchase_requests', function (Blueprint $table) {
            $table->string('service_code', 10)->nullable()->after('requested_by');
            $table->index('service_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('omsb_procurement_purchase_requests', function (Blueprint $table) {
            $table->dropIndex(['service_code']);
            $table->dropColumn('service_code');
        });
    }
};