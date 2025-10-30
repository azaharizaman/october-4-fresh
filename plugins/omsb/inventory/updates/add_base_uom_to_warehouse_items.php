<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * AddBaseUomToWarehouseItems Migration
 * 
 * Updates WarehouseItem to use Organization plugin's UOM system.
 * Adds base_uom_id and removes old inventory-specific UOM references.
 * Ensures quantity_on_hand is always stored in base UOM.
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
        Schema::table('omsb_inventory_warehouse_items', function(Blueprint $table) {
            // Add base_uom_id referencing Organization plugin
            // This will be the UOM for quantity_on_hand (ALWAYS in base units)
            $table->unsignedBigInteger('base_uom_id')->nullable()
                ->after('warehouse_id')
                ->comment('Base UOM for quantity_on_hand (always in base units)');
            
            // Add display_uom_id for warehouse preference
            $table->unsignedBigInteger('display_uom_id')->nullable()
                ->after('base_uom_id')
                ->comment('Warehouse preference for displaying quantities');
            
            // Foreign keys referencing Organization plugin
            $table->foreign('base_uom_id', 'fk_wh_item_base_uom')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->restrictOnDelete();
                
            $table->foreign('display_uom_id', 'fk_wh_item_disp_uom')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->nullOnDelete();
            
            // Add indexes
            $table->index('base_uom_id', 'idx_warehouse_items_base_uom');
            $table->index('display_uom_id', 'idx_warehouse_items_display_uom');
        });
        
        // Note: Old UOM columns (default_uom_id, primary_inventory_uom_id) will be deprecated
        // They reference omsb_inventory_unit_of_measures which should be migrated to Organization
        // A separate data migration will copy these references to the new base_uom_id field
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::table('omsb_inventory_warehouse_items', function(Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign('fk_wh_item_base_uom');
            $table->dropForeign('fk_wh_item_disp_uom');
            
            // Drop indexes
            $table->dropIndex('idx_warehouse_items_base_uom');
            $table->dropIndex('idx_warehouse_items_display_uom');
            
            // Drop columns
            $table->dropColumn(['base_uom_id', 'display_uom_id']);
        });
    }
};
