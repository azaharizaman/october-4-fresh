<?php namespace Omsb\Budget\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateBudgetReallocationsTable Migration
 * 
 * Budget Reallocation is a CONTROLLED DOCUMENT - requires document number from registrar
 * Handles budget reallocations within the same site between different GL accounts
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
        Schema::create('omsb_budget_reallocations', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            
            // Document identification (controlled document)
            $table->string('document_number', 100)->unique();
            
            // Reallocation details
            $table->date('reallocation_date');
            $table->decimal('amount', 15, 2);
            
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
            
            // Foreign keys
            $table->foreignId('budget_id')
                ->constrained('omsb_budget_budgets')
                ->onDelete('restrict');
                
            $table->foreignId('from_gl_account_id')
                ->constrained('omsb_organization_gl_accounts')
                ->onDelete('restrict');
                
            $table->foreignId('to_gl_account_id')
                ->constrained('omsb_organization_gl_accounts')
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
            $table->index('document_number', 'idx_budget_reallocations_doc_num');
            $table->index('status', 'idx_budget_reallocations_status');
            $table->index('reallocation_date', 'idx_budget_reallocations_date');
            $table->index('deleted_at', 'idx_budget_reallocations_deleted_at');
            
            // Composite indexes for common queries
            $table->index(['budget_id', 'status'], 'idx_budget_reallocations_budget_status');
            $table->index(['from_gl_account_id', 'to_gl_account_id'], 'idx_budget_reallocations_gl_accounts');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_budget_reallocations');
    }
};
