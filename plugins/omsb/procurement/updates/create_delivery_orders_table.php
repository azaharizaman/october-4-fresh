<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDeliveryOrdersTable Migration
 * For non-inventory items - records expense/asset journal entries
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
        Schema::create('omsb_procurement_delivery_orders', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique();
            $table->date('delivery_date');
            $table->string('vendor_delivery_note')->nullable();
            
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'completed',
                'cancelled'
            ])->default('draft');
            
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->text('acceptance_notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('purchase_order_id')
                ->constrained('omsb_procurement_purchase_orders')
                ->onDelete('cascade');
            
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->onDelete('cascade');
            
            $table->unsignedInteger('received_by');
            $table->foreign('received_by')->references('id')->on('omsb_organization_staff')->onDelete('cascade');
            
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_do_document_number');
            $table->index('status', 'idx_do_status');
            $table->index('delivery_date', 'idx_do_delivery_date');
            $table->index('deleted_at', 'idx_do_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_delivery_orders');
    }
};
