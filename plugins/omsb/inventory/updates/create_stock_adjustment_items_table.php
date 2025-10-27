<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateStockAdjustmentItemsTable Migration
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
        Schema::create('omsb_inventory_stock_adjustment_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('quantity_before', 15, 6); // System quantity before adjustment
            $table->decimal('quantity_after', 15, 6); // Physical/corrected quantity
            $table->decimal('quantity_variance', 15, 6); // Difference (after - before)
            $table->decimal('unit_cost', 15, 6); // For valuation impact
            $table->decimal('value_impact', 15, 6); // Financial impact (variance × cost)
            $table->text('reason_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Stock Adjustment header
            $table->foreignId('stock_adjustment_id')
                ->constrained('omsb_inventory_stock_adjustments', 'id', 'fk_sa_items_sa')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse Item (SKU)
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items', 'id', 'fk_sa_items_wh_item')
                ->restrictOnDelete();
                
            // Foreign key - Adjustment UOM
            $table->foreignId('adjustment_uom_id')
                ->constrained('omsb_inventory_unit_of_measures', 'id', 'fk_sa_items_adj_uom')
                ->restrictOnDelete();
            
            // Quantities in different UOMs for conversion tracking
            $table->decimal('quantity_variance_in_uom', 15, 6); // In adjustment UOM
            $table->decimal('quantity_variance_in_default_uom', 15, 6); // Converted to default UOM
            $table->decimal('conversion_factor_used', 15, 6); // Audit trail
            
            // Indexes
            $table->index('deleted_at', 'idx_stock_adj_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_stock_adjustment_items');
    }
};
