<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWorkflowStatusesTable Migration
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
        Schema::create('omsb_workflow_statuses', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('color')->default('#6c757d'); // Bootstrap color for UI display
            $table->boolean('is_initial')->default(false); // Starting status (e.g., draft)
            $table->boolean('is_final')->default(false); // Terminal status (e.g., completed, cancelled)
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('workflow_definition_id')
                ->constrained('omsb_workflow_definitions')
                ->onDelete('cascade');
            
            // Indexes
            $table->index('code', 'idx_workflow_statuses_code');
            $table->index('is_initial', 'idx_workflow_statuses_initial');
            $table->index('is_final', 'idx_workflow_statuses_final');
            $table->index('deleted_at', 'idx_workflow_statuses_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_workflow_statuses');
    }
};
