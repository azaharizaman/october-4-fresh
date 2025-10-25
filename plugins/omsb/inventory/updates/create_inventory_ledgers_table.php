<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateInventoryLedgersTable Migration
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
        Schema::create('omsb_inventory_inventory_ledgers', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_type'); // Polymorphic - source document class
            $table->unsignedBigInteger('document_id'); // Polymorphic - source document ID
            $table->string('transaction_type', 20); // receipt, issue, adjustment, transfer_in, transfer_out
            $table->decimal('quantity_change', 15, 6); // +/- value
            $table->decimal('quantity_before', 15, 6); // Balance before transaction
            $table->decimal('quantity_after', 15, 6); // Balance after transaction
            $table->decimal('unit_cost', 15, 6)->nullable(); // Cost per unit for valuation
            $table->decimal('total_cost', 15, 6)->nullable(); // quantity × unit_cost
            $table->string('reference_number')->nullable(); // Document reference
            $table->timestamp('transaction_date'); // When transaction occurred
            $table->text('notes')->nullable(); // Additional context
            $table->boolean('is_locked')->default(false); // Prevents modification after month-end
            $table->timestamps();
            
            // Foreign key - Warehouse Item
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->restrictOnDelete(); // Prevent deletion of items with history
                
            // Foreign key - Transaction UOM
            $table->foreignId('transaction_uom_id')
                ->constrained('omsb_inventory_unit_of_measures')
                ->restrictOnDelete();
                
            // Foreign key - Quantity in default UOM for reporting
            $table->decimal('quantity_in_transaction_uom', 15, 6); // Actual qty in warehouse UOM
            $table->decimal('quantity_in_default_uom', 15, 6); // Converted qty for HQ reporting
            $table->decimal('conversion_factor_used', 15, 6); // Audit trail of conversion rate
            
            // Foreign key - Inventory Period (optional)
            $table->foreignId('inventory_period_id')
                ->nullable()
                ->constrained('omsb_inventory_inventory_periods')
                ->restrictOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index(['document_type', 'document_id'], 'idx_ledger_document');
            $table->index('transaction_type', 'idx_ledger_transaction_type');
            $table->index('transaction_date', 'idx_ledger_transaction_date');
            $table->index('is_locked', 'idx_ledger_locked');
            $table->index(['warehouse_item_id', 'transaction_date'], 'idx_ledger_item_date');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_inventory_ledgers');
    }
};
