<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePhysicalCountsTable Migration
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
        Schema::create('omsb_inventory_physical_counts', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('count_number')->unique(); // Document number from Registrar
            $table->string('document_number', 120)->nullable(); // Controlled document number
            $table->date('count_date');
            $table->string('count_type', 20); // full, cycle, spot
            $table->timestamp('cut_off_time')->nullable(); // System snapshot time
            $table->integer('total_items_counted')->default(0);
            $table->integer('variance_count')->default(0); // Items with variances
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('scheduled'); // scheduled, in_progress, completed, variance_review
            $table->string('previous_status', 50)->nullable(); // Track previous status for audit
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse (where count occurs)
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->restrictOnDelete();
            
            // Foreign key - Document Registry (for controlled document tracking)
            $table->foreignId('registry_id')
                ->nullable()
                ->constrained('omsb_registrar_document_registries')
                ->nullOnDelete();
                
            // Foreign key - Initiated by staff
            $table->foreignId('initiated_by')
                ->constrained('omsb_organization_staff')
                ->restrictOnDelete();
                
            // Foreign key - Supervisor
            $table->foreignId('supervisor')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('count_number', 'idx_physical_count_number');
            $table->index('document_number', 'idx_physical_count_document_number');
            $table->index('count_date', 'idx_physical_count_date');
            $table->index('count_type', 'idx_physical_count_type');
            $table->index('status', 'idx_physical_count_status');
            $table->index('deleted_at', 'idx_physical_count_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_physical_counts');
    }
};
