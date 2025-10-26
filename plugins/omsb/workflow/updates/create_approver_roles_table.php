<?php namespace Omsb\Workflow\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateApproverRolesTable Migration
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
        Schema::create('omsb_workflow_approver_roles', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('workflow_definition_id')
                ->constrained('omsb_workflow_definitions')
                ->onDelete('cascade');
            
            // Indexes
            $table->index('code', 'idx_approver_roles_code');
            $table->index('is_active', 'idx_approver_roles_active');
            $table->index('deleted_at', 'idx_approver_roles_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_workflow_approver_roles');
    }
};
