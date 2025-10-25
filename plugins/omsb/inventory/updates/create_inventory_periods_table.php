<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateInventoryPeriodsTable Migration
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
        Schema::create('omsb_inventory_inventory_periods', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('period_code', 20)->unique(); // e.g., "2024-01", "2024-Q1"
            $table->string('period_name'); // e.g., "January 2024", "Q1 2024"
            $table->string('period_type', 20); // monthly, quarterly, yearly
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open'); // open, closing, closed, locked
            $table->integer('fiscal_year');
            $table->string('valuation_method', 20)->default('FIFO'); // FIFO, LIFO, Average
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->boolean('is_adjustment_period')->default(false); // For year-end adjustments
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Previous period (for opening balance transfers)
            $table->foreignId('previous_period_id')
                ->nullable()
                ->constrained('omsb_inventory_inventory_periods')
                ->nullOnDelete();
            
            // Foreign key - Closed by user (backend_users)
            $table->unsignedInteger('closed_by')->nullable();
            $table->foreign('closed_by')->references('id')->on('backend_users')->nullOnDelete();
                
            // Foreign key - Locked by user (backend_users)
            $table->unsignedInteger('locked_by')->nullable();
            $table->foreign('locked_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('period_code', 'idx_inv_period_code');
            $table->index('period_type', 'idx_inv_period_type');
            $table->index('status', 'idx_inv_period_status');
            $table->index('fiscal_year', 'idx_inv_period_fiscal_year');
            $table->index(['start_date', 'end_date'], 'idx_inv_period_dates');
            $table->index('deleted_at', 'idx_inv_period_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_inventory_periods');
    }
};
