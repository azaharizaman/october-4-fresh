<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWorkflowInstancesTable Migration
 * 
 * Manages ongoing approval workflow instances, not approval definitions.
 * Approval definitions are managed by Organization plugin.
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
        Schema::create('omsb_workflow_instances', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('workflow_code')->unique(); // Unique identifier for this workflow instance
            $table->string('status', 20)->default('pending'); // pending, in_progress, completed, failed, cancelled
            
            // Document being processed
            $table->string('document_type'); // e.g., 'purchase_request', 'stock_adjustment'
            $table->morphs('workflowable'); // Links to the actual document (morphTo)
            $table->decimal('document_amount', 15, 2)->nullable(); // Amount for approval routing
            
            // Workflow progress tracking
            $table->string('current_step', 100)->nullable(); // Current approval step
            $table->integer('total_steps_required')->default(1); // How many approval steps needed
            $table->integer('steps_completed')->default(0); // How many steps completed
            $table->json('approval_path')->nullable(); // Array of approval rule IDs in sequence
            
            // Timing and deadlines
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('due_at')->nullable(); // When workflow should complete
            $table->boolean('is_overdue')->default(false);
            
            // Current approval requirements (from Organization approval rules)
            $table->string('current_approval_type', 20)->default('single'); // single, quorum, majority
            $table->integer('approvals_required')->default(1); // How many approvals needed for current step
            $table->integer('approvals_received')->default(0); // How many received so far
            $table->integer('rejections_received')->default(0); // How many rejections received
            
            // Escalation and delegation
            $table->boolean('is_escalated')->default(false);
            $table->timestamp('escalated_at')->nullable();
            $table->text('escalation_reason')->nullable();
            
            // Comments and notes
            $table->text('workflow_notes')->nullable();
            $table->json('metadata')->nullable(); // Additional workflow data
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Created by user (who started this workflow)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Foreign key - Current approval rule being processed
            $table->foreignId('current_approval_rule_id')
                ->nullable()
                ->constrained('omsb_organization_approvals')
                ->nullOnDelete();
            
            // Foreign key - Site context
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
            
            // Indexes for performance
            $table->index('workflow_code', 'idx_workflow_instances_code');
            $table->index('status', 'idx_workflow_instances_status');
            $table->index(['document_type', 'workflowable_type'], 'idx_workflow_instances_document');
            $table->index('current_step', 'idx_workflow_instances_step');
            $table->index(['due_at', 'is_overdue'], 'idx_workflow_instances_due');
            $table->index(['started_at', 'completed_at'], 'idx_workflow_instances_timing');
            $table->index('is_escalated', 'idx_workflow_instances_escalated');
            $table->index('deleted_at', 'idx_workflow_instances_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_workflow_instances');
    }
};
