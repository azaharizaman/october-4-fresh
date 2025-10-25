<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateUnitOfMeasuresTable Migration
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
        Schema::create('omsb_inventory_unit_of_measures', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code', 10)->unique(); // EA, BOX, KG, etc.
            $table->string('name'); // Each, Box, Kilogram, etc.
            $table->string('symbol', 10)->nullable(); // pcs, kg, m, etc.
            $table->string('uom_type', 20); // count, weight, volume, length, area
            $table->boolean('is_base_unit')->default(false); // For conversion reference
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_uom_code');
            $table->index('uom_type', 'idx_uom_type');
            $table->index('is_active', 'idx_uom_active');
            $table->index('deleted_at', 'idx_uom_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_unit_of_measures');
    }
};
