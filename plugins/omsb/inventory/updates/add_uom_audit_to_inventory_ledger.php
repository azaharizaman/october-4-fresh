<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * AddUomAuditToInventoryLedger Migration
 * 
 * Adds base_uom_id and transaction_uom_id to inventory ledger for complete audit trail.
 * Tracks both the base UOM for normalized storage and original transaction UOM.
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
        Schema::table('omsb_inventory_inventory_ledgers', function(Blueprint $table) {
            // Base UOM (matches WarehouseItem base_uom_id)
            // quantity_change and balance are ALWAYS in this UOM
            $table->unsignedBigInteger('base_uom_id')->nullable()
                ->after('warehouse_item_id')
                ->comment('Base UOM (matches WarehouseItem base_uom_id)');
            
            // Ensure quantity columns have proper comment
            // Note: We're not changing the column type, just adding clarity
            
            // Audit trail: Original transaction UOM for transparency
            $table->unsignedBigInteger('original_transaction_uom_id')->nullable()
                ->after('conversion_factor_used')
                ->comment('Original UOM used in transaction (audit trail)');
            
            $table->decimal('original_transaction_quantity', 15, 6)->nullable()
                ->after('original_transaction_uom_id')
                ->comment('Original quantity in transaction UOM (audit trail)');
            
            // Foreign keys referencing Organization plugin
            $table->foreign('base_uom_id', 'fk_ledger_base_uom')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->restrictOnDelete();
                
            $table->foreign('original_transaction_uom_id', 'fk_ledger_orig_txn_uom')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->nullOnDelete();
            
            // Add indexes
            $table->index('base_uom_id', 'idx_ledger_base_uom');
            $table->index('original_transaction_uom_id', 'idx_ledger_orig_txn_uom');
        });
        
        // Note: Existing transaction_uom_id references omsb_inventory_unit_of_measures
        // A separate data migration will handle copying these references to new fields
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::table('omsb_inventory_inventory_ledgers', function(Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign('fk_ledger_base_uom');
            $table->dropForeign('fk_ledger_orig_txn_uom');
            
            // Drop indexes
            $table->dropIndex('idx_ledger_base_uom');
            $table->dropIndex('idx_ledger_orig_txn_uom');
            
            // Drop columns
            $table->dropColumn([
                'base_uom_id', 
                'original_transaction_uom_id', 
                'original_transaction_quantity'
            ]);
        });
    }
};
