<?php namespace Omsb\Budget\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateBudgetAdjustmentsTable Migration
 * 
 * Budget Adjustment is a CONTROLLED DOCUMENT - requires document number from registrar
 * Handles budget amount modifications (increase or decrease)
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
        Schema::create('omsb_budget_adjustments', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            
            // Document identification (controlled document)
            $table->string('document_number', 100)->unique();
            
            // Adjustment details
            $table->date('adjustment_date');
            $table->decimal('adjustment_amount', 15, 2); // Can be positive (increase) or negative (decrease)
            $table->enum('adjustment_type', ['increase', 'decrease']);
            
            // Status tracking
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'cancelled',
                'completed'
            ])->default('draft');
            
            // Reason and notes
            $table->string('reason', 500);
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys - Budget reference
            $table->foreignId('budget_id')
                ->constrained('omsb_budget_budgets')
                ->onDelete('restrict');
            
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
            $table->index('document_number', 'idx_budget_adjustments_doc_num');
            $table->index('adjustment_type', 'idx_budget_adjustments_type');
            $table->index('status', 'idx_budget_adjustments_status');
            $table->index('adjustment_date', 'idx_budget_adjustments_date');
            $table->index('deleted_at', 'idx_budget_adjustments_deleted_at');
            
            // Composite indexes for common queries
            $table->index(['budget_id', 'status'], 'idx_budget_adjustments_budget_status');
            $table->index(['budget_id', 'adjustment_type'], 'idx_budget_adjustments_budget_type');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_budget_adjustments');
    }
};
