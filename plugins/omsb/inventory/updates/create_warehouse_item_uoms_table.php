<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWarehouseItemUOMsTable Migration
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
        Schema::create('omsb_inventory_warehouse_item_uoms', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->boolean('is_primary')->default(false); // Main UOM for this warehouse
            $table->boolean('is_count_enabled')->default(true); // Can be used in physical counts
            $table->boolean('is_transaction_enabled')->default(true); // Can be used in transactions
            $table->decimal('conversion_to_default_factor', 15, 6); // To HQ's default UOM
            $table->integer('min_quantity_precision')->default(0); // Decimal places allowed
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse Item
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->cascadeOnDelete();
                
            // Foreign key - UOM
            $table->foreignId('uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Unique constraint - one UOM per warehouse item
            $table->unique(['warehouse_item_id', 'uom_id', 'deleted_at'], 'idx_warehouse_item_uom_unique');
            
            // Indexes
            $table->index('is_primary', 'idx_warehouse_item_uom_primary');
            $table->index('is_active', 'idx_warehouse_item_uom_active');
            $table->index('deleted_at', 'idx_warehouse_item_uom_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_warehouse_item_uoms');
    }
};
