<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateInventoryValuationItemsTable Migration
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
        Schema::create('omsb_inventory_inventory_valuation_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('quantity_on_hand', 15, 6); // Snapshot QoH at valuation date
            $table->decimal('unit_cost', 15, 6); // Calculated unit cost based on method
            $table->decimal('total_value', 15, 2); // quantity × unit_cost
            $table->json('cost_layers')->nullable(); // For FIFO/LIFO tracking (cost, qty, date)
            $table->decimal('average_cost', 15, 6)->nullable(); // For average cost method
            $table->integer('transaction_count')->default(0); // Number of movements in period
            $table->timestamps();
            
            // Foreign key - Inventory Valuation header
            $table->foreignId('inventory_valuation_id')
                ->constrained('omsb_inventory_inventory_valuations')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse Item (SKU)
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->restrictOnDelete();
            
            // Indexes
            $table->index('quantity_on_hand', 'idx_inv_valuation_items_qoh');
            $table->index('unit_cost', 'idx_inv_valuation_items_cost');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_inventory_valuation_items');
    }
};
