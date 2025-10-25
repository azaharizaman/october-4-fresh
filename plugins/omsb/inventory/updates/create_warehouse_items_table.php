<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWarehouseItemsTable Migration
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
        Schema::create('omsb_inventory_warehouse_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('quantity_on_hand', 15, 6)->default(0); // Current stock level
            $table->decimal('quantity_reserved', 15, 6)->default(0); // Allocated but not issued
            $table->decimal('quantity_available', 15, 6)->storedAs('quantity_on_hand - quantity_reserved'); // Computed
            $table->decimal('minimum_stock_level', 15, 6)->default(0); // Reorder point
            $table->decimal('maximum_stock_level', 15, 6)->nullable(); // Stock ceiling
            $table->string('barcode')->nullable(); // Warehouse-specific barcode
            $table->string('bin_location')->nullable(); // Storage location within warehouse
            $table->boolean('serial_tracking_enabled')->default(false);
            $table->boolean('lot_tracking_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_counted_at')->nullable(); // Last physical count
            $table->string('cost_method', 20)->default('FIFO'); // FIFO, LIFO, Average
            $table->boolean('allows_multiple_uoms')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Purchaseable Item (from Procurement plugin)
            // NOTE: This FK references Procurement plugin - needs to be created there first
            $table->foreignId('purchaseable_item_id')
                ->constrained('omsb_procurement_purchaseable_items')
                ->cascadeOnDelete();
            
            // Foreign key - Warehouse (from Inventory plugin - now local)
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->cascadeOnDelete();
                
            // Foreign key - Default UOM (HQ's preferred UOM)
            $table->foreignId('default_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
                
            // Foreign key - Primary Inventory UOM (warehouse's main UOM)
            $table->foreignId('primary_inventory_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Unique constraint - one item per warehouse
            $table->unique(['warehouse_id', 'purchaseable_item_id', 'deleted_at'], 'idx_warehouse_item_unique');
            
            // Indexes
            $table->index('is_active', 'idx_warehouse_items_active');
            $table->index('quantity_on_hand', 'idx_warehouse_items_qoh');
            $table->index('barcode', 'idx_warehouse_items_barcode');
            $table->index('deleted_at', 'idx_warehouse_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_warehouse_items');
    }
};
