<?php namespace Omsb\Budget\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateBudgetTransfersTable Migration
 * 
 * Budget Transfer is a CONTROLLED DOCUMENT - requires document number from registrar
 * Handles intersite budget transfers (between different sites)
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
        Schema::create('omsb_budget_transfers', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            
            // Document identification (controlled document)
            $table->string('document_number', 100)->unique();
            
            // Transfer details
            $table->enum('transfer_type', ['outward', 'inward']);
            $table->date('transfer_date');
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
            $table->string('reason', 500)->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys - Budget references
            $table->foreignId('from_budget_id')
                ->constrained('omsb_budget_budgets')
                ->onDelete('restrict');
                
            $table->foreignId('to_budget_id')
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
            $table->index('document_number', 'idx_budget_transfers_doc_num');
            $table->index('transfer_type', 'idx_budget_transfers_type');
            $table->index('status', 'idx_budget_transfers_status');
            $table->index('transfer_date', 'idx_budget_transfers_date');
            $table->index('deleted_at', 'idx_budget_transfers_deleted_at');
            
            // Composite indexes for common queries
            $table->index(['from_budget_id', 'status'], 'idx_budget_transfers_from_status');
            $table->index(['to_budget_id', 'status'], 'idx_budget_transfers_to_status');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_budget_transfers');
    }
};
