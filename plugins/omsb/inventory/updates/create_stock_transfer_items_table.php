<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateStockTransferItemsTable Migration
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
        Schema::create('omsb_inventory_stock_transfer_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('quantity_requested', 15, 6); // Original request quantity
            $table->decimal('quantity_shipped', 15, 6); // Actually shipped from source
            $table->decimal('quantity_received', 15, 6)->default(0); // Actually received at destination
            $table->decimal('unit_cost', 15, 6); // Cost per unit (for valuation)
            $table->decimal('total_cost', 15, 6); // quantity × unit_cost
            $table->string('lot_number')->nullable(); // If lot tracked
            $table->json('serial_numbers')->nullable(); // If serialized items
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Stock Transfer header
            $table->foreignId('stock_transfer_id')
                ->constrained('omsb_inventory_stock_transfers')
                ->cascadeOnDelete();
                
            // Foreign key - Source Warehouse Item (from warehouse)
            $table->foreignId('from_warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->restrictOnDelete();
                
            // Foreign key - Purchaseable Item (for destination warehouse item creation)
            $table->foreignId('purchaseable_item_id')
                ->constrained('omsb_procurement_purchaseable_items')
                ->restrictOnDelete();
                
            // Foreign key - Transfer UOM
            $table->foreignId('transfer_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
            
            // Quantities in different UOMs for conversion tracking
            $table->decimal('requested_quantity_in_uom', 15, 6); // In transfer UOM
            $table->decimal('requested_quantity_in_default_uom', 15, 6); // Converted to default UOM
            $table->decimal('shipped_quantity_in_uom', 15, 6); // Actually shipped in transfer UOM
            $table->decimal('shipped_quantity_in_default_uom', 15, 6); // Shipped converted to default UOM
            $table->decimal('received_quantity_in_uom', 15, 6); // Actually received in transfer UOM
            $table->decimal('received_quantity_in_default_uom', 15, 6); // Received converted to default UOM
            $table->decimal('conversion_factor_used', 15, 6); // Audit trail
            
            // Indexes
            $table->index('lot_number', 'idx_stock_transfer_items_lot');
            $table->index('deleted_at', 'idx_stock_transfer_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_stock_transfer_items');
    }
};
