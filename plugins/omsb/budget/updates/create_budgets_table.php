<?php namespace Omsb\Budget\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateBudgetsTable Migration
 * 
 * Budget is NOT a controlled document (no running number from registrar)
 * Budget code is free input field (e.g., BGT/SGH/FEMS/MTC/5080190/25)
 *
 * @link https://docs.octobercms.com/4.x/extend/database/structure.html
 */
return new class extends Migration
{
    /**
     * up builds the migration
     */
    public function up()
    {
        Schema::create('omsb_budget_budgets', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            
            // Budget identification
            $table->string('budget_code', 100);
            $table->string('description', 500);
            $table->integer('year'); // Budget year (e.g., 2025)
            
            // Effective period
            $table->date('effective_from');
            $table->date('effective_to');
            
            // Budget amount
            $table->decimal('allocated_amount', 15, 2); // Initial allocated amount
            
            // Status tracking
            $table->enum('status', [
                'draft',
                'approved',
                'active',
                'expired',
                'cancelled'
            ])->default('draft');
            
            // Service code (optional - references Organization ServiceSettings)
            $table->string('service_code', 10)->nullable();
            
            // Additional notes
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('gl_account_id')
                ->constrained('omsb_organization_gl_accounts')
                ->onDelete('restrict'); // Don't allow deleting GL accounts that have budgets
                
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->onDelete('cascade');
            
            // Approval tracking
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
                
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
                
            $table->timestamp('approved_at')->nullable();
            
            // Indexes for better query performance
            $table->index('budget_code', 'idx_budgets_code');
            $table->index('year', 'idx_budgets_year');
            $table->index('status', 'idx_budgets_status');
            $table->index(['effective_from', 'effective_to'], 'idx_budgets_effective_dates');
            $table->index('service_code', 'idx_budgets_service');
            $table->index('deleted_at', 'idx_budgets_deleted_at');
            
            // Composite indexes for common queries
            $table->index(['site_id', 'year', 'status'], 'idx_budgets_site_year_status');
            $table->index(['gl_account_id', 'year'], 'idx_budgets_gl_year');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_budget_budgets');
    }
};
