<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateGoodsReceiptNotesTable Migration
 * For inventory items - creates entries in inventory ledger
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
        Schema::create('omsb_procurement_goods_receipt_notes', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique();
            $table->date('receipt_date');
            $table->string('delivery_note_number')->nullable(); // Vendor's delivery note reference
            
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'completed',
                'cancelled'
            ])->default('draft');
            
            $table->text('notes')->nullable();
            $table->text('inspection_notes')->nullable();
            $table->boolean('quality_check_passed')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('purchase_order_id')
                ->constrained('omsb_procurement_purchase_orders')
                ->onDelete('cascade');
            
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->onDelete('cascade');
            
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->onDelete('cascade');
            
            $table->foreignId('received_by')
                ->constrained('omsb_organization_staff')
                ->onDelete('cascade');
            
            $table->foreignId('inspected_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_grn_document_number');
            $table->index('status', 'idx_grn_status');
            $table->index('receipt_date', 'idx_grn_receipt_date');
            $table->index('deleted_at', 'idx_grn_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_goods_receipt_notes');
    }
};
