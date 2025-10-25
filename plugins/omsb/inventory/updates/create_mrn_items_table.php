<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateMrnItemsTable Migration
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
        Schema::create('omsb_inventory_mrn_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('ordered_quantity', 15, 6); // From purchase order
            $table->decimal('delivered_quantity', 15, 6); // What vendor delivered
            $table->decimal('received_quantity', 15, 6); // What warehouse accepted
            $table->decimal('rejected_quantity', 15, 6)->default(0); // Damaged/incorrect items
            $table->string('rejection_reason')->nullable();
            $table->decimal('unit_cost', 15, 6); // Cost per unit
            $table->decimal('total_cost', 15, 6); // received_quantity × unit_cost
            $table->string('lot_number')->nullable(); // If lot tracked
            $table->date('expiry_date')->nullable(); // If applicable
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - MRN header
            $table->foreignId('mrn_id')
                ->constrained('omsb_inventory_mrns')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse Item (SKU)
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->restrictOnDelete();
                
            // Foreign key - Purchase Order Line Item (source)
            // NOTE: This FK references Procurement plugin - needs to be created there first
            $table->foreignId('purchase_order_item_id')
                ->nullable()
                ->constrained('omsb_procurement_purchase_order_items')
                ->nullOnDelete();
                
            // Foreign key - Received UOM
            $table->foreignId('received_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
            
            // Quantities in different UOMs for conversion tracking
            $table->decimal('received_quantity_in_uom', 15, 6); // In received UOM
            $table->decimal('received_quantity_in_default_uom', 15, 6); // Converted to default UOM
            $table->decimal('conversion_factor_used', 15, 6); // Audit trail
            
            // Indexes
            $table->index('lot_number', 'idx_mrn_items_lot');
            $table->index('expiry_date', 'idx_mrn_items_expiry');
            $table->index('deleted_at', 'idx_mrn_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_mrn_items');
    }
};
