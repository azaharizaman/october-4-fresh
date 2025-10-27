<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePhysicalCountItemsTable Migration
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
        Schema::create('omsb_inventory_physical_count_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('system_quantity', 15, 6); // Quantity per system at cutoff
            $table->decimal('counted_quantity', 15, 6); // Physical count
            $table->decimal('variance_quantity', 15, 6); // Difference (counted - system)
            $table->timestamp('count_timestamp')->nullable();
            $table->boolean('recount_required')->default(false);
            $table->string('variance_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Physical Count header
            $table->foreignId('physical_count_id')
                ->constrained('omsb_inventory_physical_counts', 'id', 'fk_pc_items_pc')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse Item (SKU)
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items', 'id', 'fk_pc_items_wh_item')
                ->restrictOnDelete();
                
            // Foreign key - Counter staff
            $table->foreignId('counter_staff_id')
                ->nullable()
                ->constrained('omsb_organization_staff', 'id', 'fk_pc_items_counter')
                ->nullOnDelete();
                
            // Foreign key - Count UOM
            $table->foreignId('count_uom_id')
                ->constrained('omsb_inventory_unit_of_measures', 'id', 'fk_pc_items_count_uom')
                ->restrictOnDelete();
            
            // Quantities in different UOMs for conversion tracking
            $table->decimal('counted_quantity_in_count_uom', 15, 6); // What was physically counted
            $table->decimal('counted_quantity_in_default_uom', 15, 6); // Converted for system comparison
            $table->decimal('conversion_factor_used', 15, 6); // Audit trail
            
            // Indexes
            $table->index('recount_required', 'idx_physical_count_items_recount');
            $table->index('deleted_at', 'idx_physical_count_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_physical_count_items');
    }
};
