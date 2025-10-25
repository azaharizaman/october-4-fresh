<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateMrisTable Migration
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
        Schema::create('omsb_inventory_mris', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('mri_number')->unique(); // Document number from Registrar
            $table->date('issue_date');
            $table->date('requested_date')->nullable(); // When initially requested
            $table->string('issue_purpose'); // maintenance, operation, project, etc.
            $table->string('cost_center')->nullable(); // For cost allocation
            $table->string('project_code')->nullable(); // If project-related
            $table->text('remarks')->nullable();
            $table->decimal('total_issue_value', 15, 2)->default(0); // Total value of goods issued
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, completed
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse (where goods are issued from)
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->restrictOnDelete();
                
            // Foreign key - Requested by staff
            // NOTE: This FK references Organization plugin - needs to be created there first
            $table->foreignId('requested_by')
                ->constrained('omsb_organization_staff')
                ->restrictOnDelete();
                
            // Foreign key - Issued by staff (warehouse personnel)
            $table->foreignId('issued_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
                
            // Foreign key - Approved by staff
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
                
            // Foreign key - Department/Site requesting the materials
            $table->foreignId('requesting_site_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('mri_number', 'idx_mri_number');
            $table->index('issue_date', 'idx_mri_issue_date');
            $table->index('status', 'idx_mri_status');
            $table->index('issue_purpose', 'idx_mri_purpose');
            $table->index('deleted_at', 'idx_mri_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_mris');
    }
};
