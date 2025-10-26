<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWorkflowInstancesTable Migration
 * Tracks individual document workflows - history of status transitions
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
            
            // Polymorphic relationship to document
            $table->string('workflowable_type');
            $table->unsignedBigInteger('workflowable_id');
            
            $table->string('action'); // submit, approve, reject, cancel, etc.
            $table->text('comments')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();
            
            // Foreign keys
            $table->foreignId('workflow_definition_id')
                ->constrained('omsb_workflow_definitions')
                ->onDelete('cascade');
            
            $table->foreignId('from_status_id')
                ->nullable()
                ->constrained('omsb_workflow_statuses')
                ->nullOnDelete();
            
            $table->foreignId('to_status_id')
                ->constrained('omsb_workflow_statuses')
                ->onDelete('cascade');
            
            $table->foreignId('transition_id')
                ->nullable()
                ->constrained('omsb_workflow_transitions')
                ->nullOnDelete();
            
            $table->unsignedInteger('performed_by');
            $table->foreign('performed_by')->references('id')->on('backend_users')->onDelete('cascade');
            
            // Indexes
            $table->index(['workflowable_type', 'workflowable_id'], 'idx_workflow_instances_workflowable');
            $table->index('transitioned_at', 'idx_workflow_instances_transitioned_at');
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
