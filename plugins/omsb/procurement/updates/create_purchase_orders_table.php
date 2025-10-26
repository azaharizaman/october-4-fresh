<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePurchaseOrdersTable Migration
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
        Schema::create('omsb_procurement_purchase_orders', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique();
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->date('actual_delivery_date')->nullable();
            
            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected',
                'sent_to_vendor',
                'partially_received',
                'fully_received',
                'cancelled',
                'completed'
            ])->default('draft');
            
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            
            $table->enum('payment_terms', ['cod', 'net_15', 'net_30', 'net_45', 'net_60', 'net_90'])->nullable();
            $table->string('delivery_address')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            
            // Approval tracking
            $table->unsignedInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('vendor_id')
                ->constrained('omsb_procurement_vendors')
                ->onDelete('cascade');
            
            $table->foreignId('vendor_quotation_id')
                ->nullable()
                ->constrained('omsb_procurement_vendor_quotations')
                ->nullOnDelete();
            
            $table->foreignId('purchase_request_id')
                ->nullable()
                ->constrained('omsb_procurement_purchase_requests')
                ->nullOnDelete();
            
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->onDelete('cascade');
            
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_po_document_number');
            $table->index('status', 'idx_po_status');
            $table->index('order_date', 'idx_po_order_date');
            $table->index('deleted_at', 'idx_po_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_purchase_orders');
    }
};
