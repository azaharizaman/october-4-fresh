<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateLotBatchesTable Migration
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
        Schema::create('omsb_inventory_lot_batches', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('lot_number'); // Lot/Batch identifier
            $table->decimal('quantity_received', 15, 5)->default(0); // Original received quantity
            $table->decimal('quantity_available', 15, 5)->default(0); // Current available quantity
            $table->date('received_date')->nullable(); // When lot was received
            $table->date('expiry_date')->nullable(); // Expiration date (for perishable items)
            $table->date('manufacture_date')->nullable(); // Manufacturing date
            $table->string('supplier_lot_number')->nullable(); // Supplier's lot reference
            $table->string('status', 20)->default('active'); // active, expired, quarantine, issued
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse Item
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->cascadeOnDelete();
                
            // Foreign key - Purchase Order Line Item (origin)
            $table->foreignId('purchase_order_line_item_id')
                ->nullable()
                ->constrained('omsb_procurement_purchase_order_line_items')
                ->nullOnDelete();
                
            // Foreign key - MRN Line Item (receipt record)
            $table->foreignId('mrn_line_item_id')
                ->nullable()
                ->constrained('omsb_inventory_mrn_line_items')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Unique constraint - one lot number per warehouse item
            $table->unique(['warehouse_item_id', 'lot_number'], 'uniq_lot_per_warehouse_item');
            
            // Indexes
            $table->index('lot_number', 'idx_lot_batches_number');
            $table->index('expiry_date', 'idx_lot_batches_expiry');
            $table->index('status', 'idx_lot_batches_status');
            $table->index('deleted_at', 'idx_lot_batches_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_lot_batches');
    }
};
