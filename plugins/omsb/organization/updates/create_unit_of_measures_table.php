<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateUnitOfMeasuresTable Migration
 * 
 * Central organizational UOM management with base UOM normalization support.
 */
class CreateUnitOfMeasuresTable extends Migration
{
    public function up()
    {
        Schema::create('omsb_organization_unit_of_measures', function (Blueprint $table) {
            $table->id();
            
            // Core identification
            $table->string('code', 10)->unique()->comment('Unique UOM code (ROLL, PACK, BOX, DRUM, EA, KG, etc.)');
            $table->string('name')->comment('Display name (Roll, Pack of 6, Box, Drum, Each, Kilogram, etc.)');
            $table->string('symbol', 10)->nullable()->comment('Symbol notation (pcs, kg, m, etc.)');
            $table->enum('uom_type', ['count', 'weight', 'volume', 'length', 'area'])
                ->comment('Type category for validation');
            
            // Base UOM normalization
            $table->unsignedBigInteger('base_uom_id')->nullable()
                ->comment('Self-reference to base UOM (null if this IS the base)');
            $table->decimal('conversion_to_base_factor', 15, 6)->nullable()
                ->comment('Factor to convert to base (e.g., 1 Box = 12 Rolls, factor = 12)');
            
            // Organizational flags
            $table->boolean('for_purchase')->default(false)
                ->comment('Available for procurement transactions');
            $table->boolean('for_inventory')->default(false)
                ->comment('Available for inventory transactions');
            $table->boolean('is_approved')->default(false)
                ->comment('Organizational approval flag');
            $table->boolean('is_active')->default(true)
                ->comment('Active status');
            
            // Precision and description
            $table->tinyInteger('decimal_places')->nullable()->default(2)
                ->comment('Decimal precision (0 for count, 2 for weight, etc.)');
            $table->text('description')->nullable()
                ->comment('Additional notes');
            
            // Audit fields
            $table->unsignedInteger('created_by')->nullable()
                ->comment('Backend user who created this');
            $table->unsignedInteger('approved_by')->nullable()
                ->comment('Backend user who approved this');
            $table->timestamp('approved_at')->nullable()
                ->comment('Approval timestamp');
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code');
            $table->index('uom_type');
            $table->index('base_uom_id');
            $table->index(['for_purchase', 'is_active', 'is_approved'], 'idx_uom_purchase');
            $table->index(['for_inventory', 'is_active', 'is_approved'], 'idx_uom_inventory');
            $table->index('is_active');
            $table->index('deleted_at');
            
            // Foreign keys
            $table->foreign('base_uom_id', 'fk_uom_base')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->nullOnDelete();
            $table->foreign('created_by', 'fk_uom_creator')
                ->references('id')->on('backend_users')
                ->nullOnDelete();
            $table->foreign('approved_by', 'fk_uom_approver')
                ->references('id')->on('backend_users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('omsb_organization_unit_of_measures');
    }
}
