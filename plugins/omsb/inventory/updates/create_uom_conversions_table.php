<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateUOMConversionsTable Migration
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
        Schema::create('omsb_inventory_uom_conversions', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->decimal('conversion_factor', 15, 6); // from_qty * factor = to_qty
            $table->boolean('is_bidirectional')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable(); // e.g., "1 Box = 24 Rolls"
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys - UOM relationships
            $table->foreignId('from_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->cascadeOnDelete();
                
            $table->foreignId('to_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->cascadeOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Unique constraint - prevent duplicate conversions
            $table->unique(['from_uom_id', 'to_uom_id', 'deleted_at'], 'idx_uom_conversion_unique');
            
            // Indexes
            $table->index('is_active', 'idx_uom_conv_active');
            $table->index(['effective_from', 'effective_to'], 'idx_uom_conv_dates');
            $table->index('deleted_at', 'idx_uom_conv_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_uom_conversions');
    }
};
