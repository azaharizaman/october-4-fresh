<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateMrnReturnsTable Migration
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
        Schema::create('omsb_inventory_mrn_returns', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique(); // From Registrar plugin
            $table->date('return_date'); // When return was processed
            $table->string('return_reason'); // Return reason code/category
            $table->text('remarks')->nullable(); // Additional return notes
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, rejected, completed
            $table->string('previous_status', 50)->nullable(); // Track previous status for audit
            $table->decimal('total_amount', 15, 2)->default(0); // Total return value
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Original MRN being returned
            $table->foreignId('original_mrn_id')
                ->constrained('omsb_inventory_mrns')
                ->cascadeOnDelete();
            
            // Foreign key - Document Registry (for controlled document tracking)
            $table->foreignId('registry_id')
                ->nullable()
                ->constrained('omsb_registrar_document_registries')
                ->nullOnDelete();
                
            // Foreign key - Return to Vendor (Supplier)
            $table->foreignId('vendor_id')
                ->constrained('omsb_procurement_vendors')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse (where items are being returned from)
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->cascadeOnDelete();
                
            // Foreign key - Site
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->cascadeOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Foreign key - Approved by user (backend_users)
            $table->unsignedInteger('approved_by')->nullable();
            $table->foreign('approved_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_mrn_returns_doc_number');
            $table->index('return_date', 'idx_mrn_returns_date');
            $table->index('status', 'idx_mrn_returns_status');
            $table->index(['vendor_id', 'return_date'], 'idx_mrn_returns_vendor_date');
            $table->index('deleted_at', 'idx_mrn_returns_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_mrn_returns');
    }
};
