<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWorkflowTransitionsTable Migration
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
        Schema::create('omsb_workflow_transitions', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_comment')->default(false); // Force user to add comment
            $table->boolean('can_reject')->default(false); // Can transition be rejected
            $table->string('rejection_status_code')->nullable(); // Status to revert to on rejection
            $table->decimal('min_amount', 15, 2)->nullable(); // Minimum transaction amount for this transition
            $table->decimal('max_amount', 15, 2)->nullable(); // Maximum transaction amount for this transition
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('workflow_definition_id')
                ->constrained('omsb_workflow_definitions')
                ->onDelete('cascade');
            
            $table->foreignId('from_status_id')
                ->constrained('omsb_workflow_statuses')
                ->onDelete('cascade');
            
            $table->foreignId('to_status_id')
                ->constrained('omsb_workflow_statuses')
                ->onDelete('cascade');
            
            $table->foreignId('approver_role_id')
                ->nullable()
                ->constrained('omsb_workflow_approver_roles')
                ->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_workflow_transitions_code');
            $table->index(['from_status_id', 'to_status_id'], 'idx_workflow_transitions_from_to');
            $table->index('deleted_at', 'idx_workflow_transitions_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_workflow_transitions');
    }
};
