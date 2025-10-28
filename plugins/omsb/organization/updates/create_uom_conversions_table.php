<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateUOMConversionsTable Migration
 * 
 * Direct conversion rules between UOMs (complements base UOM normalization).
 */
class CreateUOMConversionsTable extends Migration
{
    public function up()
    {
        Schema::create('omsb_organization_uom_conversions', function (Blueprint $table) {
            $table->id();
            
            // Conversion definition
            $table->unsignedBigInteger('from_uom_id')
                ->comment('Source UOM');
            $table->unsignedBigInteger('to_uom_id')
                ->comment('Target UOM');
            $table->decimal('conversion_factor', 15, 6)
                ->comment('Multiplier (from_qty * factor = to_qty)');
            $table->boolean('is_bidirectional')->default(false)
                ->comment('Can convert in both directions');
            
            // Effectiveness period
            $table->timestamp('effective_from')->nullable()
                ->comment('Start date for conversion');
            $table->timestamp('effective_to')->nullable()
                ->comment('End date for conversion');
            
            // Description and status
            $table->text('notes')->nullable()
                ->comment('Conversion description (e.g., "1 Box = 12 Packs of 6 Rolls")');
            $table->boolean('is_active')->default(true)
                ->comment('Active status');
            $table->boolean('is_approved')->default(false)
                ->comment('Organizational approval flag');
            
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
            $table->index(['from_uom_id', 'to_uom_id'], 'idx_uom_conv_pair');
            $table->index('from_uom_id');
            $table->index('to_uom_id');
            $table->index(['is_active', 'is_approved'], 'idx_uom_conv_active');
            $table->index(['effective_from', 'effective_to'], 'idx_uom_conv_effective');
            $table->index('deleted_at');
            
            // Unique constraint: one active conversion per direction
            $table->unique(['from_uom_id', 'to_uom_id', 'deleted_at'], 'uk_uom_conv_unique');
            
            // Foreign keys
            $table->foreign('from_uom_id', 'fk_uom_conv_from')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->cascadeOnDelete();
            $table->foreign('to_uom_id', 'fk_uom_conv_to')
                ->references('id')->on('omsb_organization_unit_of_measures')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'fk_uom_conv_creator')
                ->references('id')->on('backend_users')
                ->nullOnDelete();
            $table->foreign('approved_by', 'fk_uom_conv_approver')
                ->references('id')->on('backend_users')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('omsb_organization_uom_conversions');
    }
}
