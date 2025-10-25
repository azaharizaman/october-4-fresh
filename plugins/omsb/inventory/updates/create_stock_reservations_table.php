<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateStockReservationsTable Migration
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
        Schema::create('omsb_inventory_stock_reservations', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('reservation_number')->unique(); // Document number
            $table->decimal('reserved_quantity', 15, 6);
            $table->string('reservation_type', 30); // sales_order, work_order, transfer_request
            $table->string('reference_document_type'); // Polymorphic
            $table->unsignedBigInteger('reference_document_id'); // Polymorphic
            $table->timestamp('reserved_at');
            $table->timestamp('expires_at')->nullable(); // Auto-release date
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active'); // active, fulfilled, expired, cancelled
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse Item
            $table->foreignId('warehouse_item_id')
                ->constrained('omsb_inventory_warehouse_items')
                ->restrictOnDelete();
                
            // Foreign key - Reserved by staff
            $table->foreignId('reserved_by')
                ->constrained('omsb_organization_staff')
                ->restrictOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('reservation_number', 'idx_stock_res_number');
            $table->index('reservation_type', 'idx_stock_res_type');
            $table->index('status', 'idx_stock_res_status');
            $table->index(['reference_document_type', 'reference_document_id'], 'idx_stock_res_document');
            $table->index('expires_at', 'idx_stock_res_expires');
            $table->index('deleted_at', 'idx_stock_res_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_stock_reservations');
    }
};
