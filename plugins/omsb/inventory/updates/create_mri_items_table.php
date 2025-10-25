<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateMriItemsTable Migration
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
        Schema::create('omsb_inventory_mri_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('requested_quantity', 15, 6); // Original request quantity
            $table->decimal('approved_quantity', 15, 6); // Approved for issue
            $table->decimal('issued_quantity', 15, 6); // Actually issued
            $table->decimal('unit_cost', 15, 6); // Cost per unit (for valuation)
            $table->decimal('total_cost', 15, 6); // issued_quantity × unit_cost
            $table->string('lot_number')->nullable(); // If lot tracked
            $table->json('serial_numbers')->nullable(); // If serialized items
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - MRI header
            $table->foreignId('mri_id')
                ->constrained('omsb_inventory_mris')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse Item (SKU)
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->restrictOnDelete();
                
            // Foreign key - Issue UOM
            $table->foreignId('issue_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
            
            // Quantities in different UOMs for conversion tracking
            $table->decimal('issued_quantity_in_uom', 15, 6); // In issue UOM
            $table->decimal('issued_quantity_in_default_uom', 15, 6); // Converted to default UOM
            $table->decimal('conversion_factor_used', 15, 6); // Audit trail
            
            // Indexes
            $table->index('lot_number', 'idx_mri_items_lot');
            $table->index('deleted_at', 'idx_mri_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_mri_items');
    }
};
