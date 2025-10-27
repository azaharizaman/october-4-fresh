<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Create Financial Periods Table
 * 
 * Business Context:
 * - Manages accounting/financial periods for GL posting, budgets, and financial compliance
 * - Unlike InventoryPeriod (operational), FinancialPeriod enforces accounting rules
 * - Supports monthly, quarterly, and yearly periods with fiscal year tracking
 * - Multi-stage closing: soft close (AP/AR) → full close (all GL) → lock (permanent)
 * - Year-end adjustment periods (13th period) supported
 * - Prevents backdated posting after period close (unless explicitly allowed)
 * 
 * Integration Points:
 * - Budget plugin: Each budget allocation tied to financial period
 * - Procurement plugin: PO/Invoice posting date validation
 * - Inventory plugin: Coordinates with InventoryPeriod for COGS/valuation
 * - GL transactions: All journal entries validated against period status
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('omsb_organization_financial_periods', function (Blueprint $table) {
            $table->id();
            
            // Period identification
            $table->string('period_code', 30)->unique()->comment('Unique identifier (e.g., FY2025-Q1, FY2025-01)');
            $table->string('period_name')->comment('Display name (e.g., Q1 FY2025, January FY2025)');
            $table->enum('period_type', ['monthly', 'quarterly', 'yearly'])->default('monthly');
            
            // Period dates
            $table->date('start_date')->comment('Period start date');
            $table->date('end_date')->comment('Period end date');
            $table->unsignedSmallInteger('fiscal_year')->comment('Fiscal year (e.g., 2025)');
            $table->unsignedTinyInteger('fiscal_quarter')->nullable()->comment('Fiscal quarter (1-4) for quarterly periods');
            $table->unsignedTinyInteger('fiscal_month')->nullable()->comment('Fiscal month (1-12) or 13 for year-end adjustment');
            
            // Period status and control
            $table->enum('status', ['draft', 'open', 'soft_closed', 'closed', 'locked'])->default('draft')->comment('Period lifecycle status');
            $table->boolean('is_year_end')->default(false)->comment('Is this a year-end adjustment period (13th period)');
            $table->boolean('allow_backdated_posting')->default(false)->comment('Allow posting transactions with dates before period start');
            
            // Closing audit trail
            $table->timestamp('soft_closed_at')->nullable()->comment('When AP/AR was closed (GL still open)');
            $table->timestamp('closed_at')->nullable()->comment('When period was fully closed');
            $table->timestamp('locked_at')->nullable()->comment('When period was permanently locked');
            $table->unsignedInteger('soft_closed_by')->nullable()->comment('User who soft-closed period');
            $table->unsignedInteger('closed_by')->nullable()->comment('User who fully closed period');
            $table->unsignedInteger('locked_by')->nullable()->comment('User who locked period');
            $table->text('closing_notes')->nullable()->comment('Notes about period closing process');
            
            // Period linkage
            $table->unsignedBigInteger('previous_period_id')->nullable()->comment('Link to previous period for opening balance transfer');
            
            // Audit fields
            $table->unsignedInteger('created_by')->nullable()->comment('Backend user who created this period');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index('fiscal_year');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index('period_type');
            
            // Foreign keys
            $table->foreign('previous_period_id')->references('id')->on('omsb_organization_financial_periods')->nullOnDelete();
            $table->foreign('soft_closed_by')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreign('closed_by')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreign('locked_by')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_financial_periods');
    }
};
