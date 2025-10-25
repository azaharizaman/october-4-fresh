<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateSerialNumbersTable Migration
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
        Schema::create('omsb_inventory_serial_numbers', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('serial_number')->unique(); // Unique identifier
            $table->string('status', 20)->default('available'); // available, reserved, issued, damaged
            $table->date('received_date')->nullable(); // When item was received
            $table->date('issued_date')->nullable(); // When item was issued
            $table->string('manufacturer_serial')->nullable(); // Original manufacturer serial
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse Item
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->cascadeOnDelete();
                
            // Foreign key - Last transaction reference
            $table->foreignId('last_transaction_id')
                ->nullable()
                ->constrained('omsb_inventory_inventory_ledgers')
                ->nullOnDelete();
                
            // Foreign key - Current holder (if issued to specific staff)
            $table->foreignId('current_holder_id')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('serial_number', 'idx_serial_numbers_number');
            $table->index('status', 'idx_serial_numbers_status');
            $table->index('received_date', 'idx_serial_numbers_received');
            $table->index('deleted_at', 'idx_serial_numbers_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_serial_numbers');
    }
};
