<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateMriReturnItemsTable Migration
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
        Schema::create('omsb_inventory_mri_return_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->integer('line_number'); // Line sequence within return document
            $table->decimal('return_quantity', 15, 5); // Quantity being returned
            $table->string('return_uom', 20); // Unit of measure for return
            $table->decimal('return_quantity_base_uom', 15, 5); // Converted to base UOM
            $table->string('return_reason'); // Specific reason for this line item
            $table->text('remarks')->nullable(); // Line-specific notes
            $table->string('condition', 20)->default('good'); // good, damaged, expired, unused
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - MRI Return header
            $table->foreignId('mri_return_id')
                ->constrained('omsb_inventory_mri_returns')
                ->cascadeOnDelete();
                
            // Foreign key - Original MRI Line Item being returned
            $table->foreignId('original_mri_item_id')
                ->constrained('omsb_inventory_mri_items')
                ->cascadeOnDelete();
                
            // Foreign key - Warehouse Item
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->cascadeOnDelete();
                
            // Foreign key - Purchaseable Item
            $table->foreignId('purchaseable_item_id')
                ->constrained('omsb_procurement_purchaseable_items')
                ->cascadeOnDelete();
                
            // Foreign key - UOM
            $table->foreignId('uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->cascadeOnDelete();
                
            // Foreign key - Serial Number (if applicable)
            $table->foreignId('serial_number_id')
                ->nullable()
                ->constrained('omsb_inventory_serial_numbers')
                ->nullOnDelete();
                
            // Foreign key - Lot Batch (if applicable)
            $table->foreignId('lot_batch_id')
                ->nullable()
                ->constrained('omsb_inventory_lot_batches')
                ->nullOnDelete();
            
            // Unique constraint - one line number per return document
            $table->unique(['mri_return_id', 'line_number'], 'uniq_mri_return_line_number');
            
            // Indexes
            $table->index('line_number', 'idx_mri_return_items_line_number');
            $table->index('return_quantity', 'idx_mri_return_items_qty');
            $table->index('condition', 'idx_mri_return_items_condition');
            $table->index('deleted_at', 'idx_mri_return_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_mri_return_items');
    }
};
