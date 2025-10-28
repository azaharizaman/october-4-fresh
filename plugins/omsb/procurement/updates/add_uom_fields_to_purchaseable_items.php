<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * AddUomFieldsToPurchaseableItems Migration
 * 
 * Adds base_uom_id and purchase_uom_id columns to support UOM normalization.
 * References Organization plugin's centralized UOM management.
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
        Schema::table('omsb_procurement_purchaseable_items', function(Blueprint $table) {
            // Base UOM for normalization (from Organization plugin)
            $table->unsignedBigInteger('base_uom_id')->nullable()
                ->after('unit_of_measure')
                ->comment('Base UOM for normalization (from Organization plugin)');
            
            // Preferred purchase UOM
            $table->unsignedBigInteger('purchase_uom_id')->nullable()
                ->after('base_uom_id')
                ->comment('Preferred purchase UOM');
            
            // Foreign keys referencing Organization plugin
            $table->foreign('base_uom_id', 'fk_item_base_uom')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->nullOnDelete();
                
            $table->foreign('purchase_uom_id', 'fk_item_purch_uom')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->nullOnDelete();
            
            // Add indexes for performance
            $table->index('base_uom_id', 'idx_purchaseable_items_base_uom');
            $table->index('purchase_uom_id', 'idx_purchaseable_items_purchase_uom');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::table('omsb_procurement_purchaseable_items', function(Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign('fk_item_base_uom');
            $table->dropForeign('fk_item_purch_uom');
            
            // Drop indexes
            $table->dropIndex('idx_purchaseable_items_base_uom');
            $table->dropIndex('idx_purchaseable_items_purchase_uom');
            
            // Drop columns
            $table->dropColumn(['base_uom_id', 'purchase_uom_id']);
        });
    }
};
