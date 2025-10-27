<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWorkflowActionsTable Migration
 * 
 * Tracks individual approval/rejection actions within workflow instances.
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
        Schema::create('omsb_workflow_actions', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('action', 20); // approve, reject, delegate, escalate, comment
            $table->string('step_name')->nullable(); // Name/description of this approval step
            $table->integer('step_sequence')->default(1); // Order in the workflow
            
            // Action details
            $table->text('comments')->nullable(); // Approver comments
            $table->text('rejection_reason')->nullable(); // Specific reason for rejection
            $table->boolean('is_automatic')->default(false); // Was this action automated (timeout, etc.)
            
            // Delegation details
            $table->boolean('is_delegated_action')->default(false);
            $table->text('delegation_reason')->nullable();
            
            // Timing
            $table->timestamp('action_taken_at')->nullable();
            $table->timestamp('due_at')->nullable(); // When this step was due
            $table->boolean('is_overdue_action')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Workflow Instance
            $table->foreignId('workflow_instance_id')
                ->constrained('omsb_workflow_instances')
                ->cascadeOnDelete();
            
            // Foreign key - Approval Rule (from Organization plugin)
            $table->foreignId('approval_rule_id')
                ->constrained('omsb_organization_approvals')
                ->cascadeOnDelete();
            
            // Foreign key - Staff who took action
            $table->foreignId('staff_id')
                ->constrained('omsb_organization_staff')
                ->cascadeOnDelete();
            
            // Foreign key - Original staff (if delegated)
            $table->foreignId('original_staff_id')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Backend user who took action
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index(['workflow_instance_id', 'step_sequence'], 'idx_workflow_actions_instance_step');
            $table->index('action', 'idx_workflow_actions_action');
            $table->index('action_taken_at', 'idx_workflow_actions_taken_at');
            $table->index(['due_at', 'is_overdue_action'], 'idx_workflow_actions_due');
            $table->index('is_delegated_action', 'idx_workflow_actions_delegated');
            $table->index('deleted_at', 'idx_workflow_actions_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_workflow_actions');
    }
};