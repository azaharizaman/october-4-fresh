<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateStockAdjustmentsTable Migration
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
        Schema::create('omsb_inventory_stock_adjustments', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('adjustment_number')->unique(); // Document number from Registrar
            $table->date('adjustment_date');
            $table->string('reason_code', 50); // damage, theft, count_variance, expired, etc.
            $table->string('reference_document')->nullable(); // Source of adjustment
            $table->decimal('total_value_impact', 15, 2)->default(0); // Financial impact
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, completed
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse (where adjustment occurs)
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->restrictOnDelete();
                
            // Foreign key - Approved by staff
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('adjustment_number', 'idx_stock_adj_number');
            $table->index('adjustment_date', 'idx_stock_adj_date');
            $table->index('reason_code', 'idx_stock_adj_reason');
            $table->index('status', 'idx_stock_adj_status');
            $table->index('deleted_at', 'idx_stock_adj_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_stock_adjustments');
    }
};
