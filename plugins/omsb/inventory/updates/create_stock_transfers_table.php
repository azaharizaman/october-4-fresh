<?php namespace Omsb\Inventory\Updates;

use Schema;
use Illuminate\Support\Facades\DB;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateStockTransfersTable Migration
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
        Schema::create('omsb_inventory_stock_transfers', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('transfer_number')->unique(); // Document number from Registrar
            $table->string('document_number', 120)->nullable(); // Controlled document number
            $table->date('transfer_date');
            $table->date('requested_date')->nullable(); // When initially requested
            $table->date('shipped_date')->nullable();
            $table->date('received_date')->nullable();
            $table->string('transportation_method')->nullable(); // truck, courier, internal
            $table->string('tracking_number')->nullable(); // External carrier tracking
            $table->text('notes')->nullable();
            $table->decimal('total_transfer_value', 15, 2)->default(0); // Total value of goods transferred
            $table->string('status', 20)->default('draft'); // draft, approved, in_transit, received, cancelled
            $table->string('previous_status', 50)->nullable(); // Track previous status for audit
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - From Warehouse (source)
            $table->foreignId('from_warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->restrictOnDelete();
                
            // Foreign key - To Warehouse (destination)
            $table->foreignId('to_warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->restrictOnDelete();
            
            // Foreign key - Document Registry (for controlled document tracking)
            $table->foreignId('registry_id')
                ->nullable()
                ->constrained('omsb_registrar_document_registries')
                ->nullOnDelete();
                
            // Foreign key - Requested by staff
            $table->foreignId('requested_by')
                ->constrained('omsb_organization_staff')
                ->restrictOnDelete();
                
            // Foreign key - Approved by staff
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
                
            // Foreign key - Shipped by staff (from warehouse)
            $table->foreignId('shipped_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
                
            // Foreign key - Received by staff (to warehouse)
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('transfer_number', 'idx_stock_transfer_number');
            $table->index('document_number', 'idx_stock_transfer_document_number');
            $table->index('transfer_date', 'idx_stock_transfer_date');
            $table->index('status', 'idx_stock_transfer_status');
            $table->index('deleted_at', 'idx_stock_transfer_deleted_at');
        });
        
        // Add check constraint to prevent transfer to same warehouse
        DB::statement('ALTER TABLE omsb_inventory_stock_transfers ADD CONSTRAINT chk_different_warehouses CHECK (from_warehouse_id != to_warehouse_id)');
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_stock_transfers');
    }
};
