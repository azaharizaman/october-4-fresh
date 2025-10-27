<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateApprovalsTable Migration
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
        Schema::create('omsb_organization_approvals', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('document_type', 100); // e.g., 'purchase_request', 'purchase_order', 'stock_adjustment'
            $table->string('action', 100); // e.g., 'approve', 'review', 'authorize'
            
            // Value limits for approval authority
            $table->decimal('floor_limit', 15, 2)->default(0); // Minimum value this approver can handle
            $table->decimal('ceiling_limit', 15, 2)->nullable(); // Maximum value this approver can handle (null = unlimited)
            
            // Budget vs Non-budget scenarios
            $table->decimal('budget_ceiling_limit', 15, 2)->nullable(); // Max when budget covers the amount
            $table->decimal('non_budget_ceiling_limit', 15, 2)->nullable(); // Max when budget doesn't cover
            
            // Workflow states
            $table->string('from_status')->nullable(); // Current document status to transition from
            $table->string('to_status'); // Target document status after approval
            
            // Delegation support
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_delegated')->default(false);
            $table->date('delegated_from')->nullable();
            $table->date('delegated_to')->nullable();
            
            // Multi-approver support (MLAS functionality)
            $table->string('approval_type', 20)->default('single'); // single, quorum, majority, unanimous
            $table->integer('required_approvers')->default(1); // How many approvals needed
            $table->integer('eligible_approvers')->nullable(); // Total eligible approvers (for quorum)
            $table->boolean('requires_hierarchy_validation')->default(true); // Must be above creator in hierarchy
            $table->integer('minimum_hierarchy_level')->nullable(); // Minimum level in org chart
            
            // Assignment strategies
            $table->string('assignment_strategy', 20)->default('manual'); // manual, position_based, round_robin, load_balanced
            $table->boolean('is_position_based')->default(false);
            $table->boolean('allow_external_site_approvers')->default(false);
            $table->json('eligible_position_ids')->nullable(); // Array of position IDs for position-based
            $table->json('eligible_staff_ids')->nullable(); // Array of staff IDs for manual assignment
            
            // Advanced workflow settings
            $table->boolean('override_individual_limits')->default(false); // Can override staff ceiling limits
            $table->string('rejection_target_status')->nullable(); // Where to go if rejected
            $table->boolean('requires_comment_on_rejection')->default(true);
            $table->boolean('requires_comment_on_approval')->default(false);
            
            // Timeout and escalation
            $table->integer('approval_timeout_days')->nullable(); // Auto-escalate after X days
            $table->string('timeout_action', 20)->default('revert'); // revert, escalate, auto_approve
            $table->foreignId('escalation_approval_rule_id')->nullable()->constrained('omsb_organization_approvals')->nullOnDelete();
            
            // Enhanced delegation settings
            $table->boolean('allows_delegation')->default(true);
            $table->integer('max_delegation_days')->nullable();
            $table->boolean('requires_delegation_justification')->default(false);
            
            // Categorization
            $table->string('transaction_category')->nullable(); // Further categorize documents
            $table->string('budget_type')->default('All'); // 'Capital', 'Operating', 'All'
            $table->string('service_type')->default('All'); // Additional categorization
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Staff relationship
            $table->foreignId('staff_id')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->cascadeOnDelete();
            
            // Foreign key - Site relationship
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
            
            // Foreign key - Delegated staff relationship
            $table->foreignId('delegated_to_staff_id')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_approvals_code');
            $table->index(['document_type', 'action'], 'idx_approvals_document_action');
            $table->index(['floor_limit', 'ceiling_limit'], 'idx_approvals_limits');
            $table->index(['is_active', 'effective_from', 'effective_to'], 'idx_approvals_active_period');
            $table->index('deleted_at', 'idx_approvals_deleted_at');
            
            // New indexes for MLAS functionality
            $table->index('approval_type', 'idx_approvals_approval_type');
            $table->index(['required_approvers', 'eligible_approvers'], 'idx_approvals_quorum');
            $table->index('assignment_strategy', 'idx_approvals_assignment_strategy');
            $table->index('is_position_based', 'idx_approvals_position_based');
            $table->index(['approval_timeout_days', 'timeout_action'], 'idx_approvals_timeout');
            
            // Unique constraint to prevent duplicate approval definitions
            $table->unique([
                'staff_id', 
                'document_type', 
                'action', 
                'site_id', 
                'transaction_category',
                'deleted_at'
            ], 'idx_approvals_unique_definition');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_approvals');
    }
};
