<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWorkflowDefinitionsTable Migration
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
        Schema::create('omsb_workflow_definitions', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('document_type'); // Fully qualified class name
            $table->boolean('is_active')->default(true);
            $table->integer('max_approval_days')->default(30); // Days before escalation/auto-revert
            $table->boolean('requires_approval')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code', 'idx_workflow_defs_code');
            $table->index('document_type', 'idx_workflow_defs_doc_type');
            $table->index('is_active', 'idx_workflow_defs_active');
            $table->index('deleted_at', 'idx_workflow_defs_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_workflow_definitions');
    }
};
