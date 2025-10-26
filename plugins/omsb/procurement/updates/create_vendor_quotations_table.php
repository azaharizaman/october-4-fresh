<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateVendorQuotationsTable Migration
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
        Schema::create('omsb_procurement_vendor_quotations', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique();
            $table->string('vendor_quotation_number')->nullable(); // Vendor's own reference number
            $table->date('quotation_date');
            $table->date('valid_until');
            
            $table->enum('status', [
                'draft',
                'submitted',
                'under_review',
                'accepted',
                'rejected',
                'expired',
                'cancelled'
            ])->default('draft');
            
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('grand_total', 15, 2)->default(0);
            
            $table->enum('payment_terms', ['cod', 'net_15', 'net_30', 'net_45', 'net_60', 'net_90'])->nullable();
            $table->integer('delivery_lead_time_days')->nullable();
            $table->text('terms_and_conditions')->nullable();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('purchase_request_id')
                ->nullable()
                ->constrained('omsb_procurement_purchase_requests')
                ->nullOnDelete();
            
            $table->foreignId('vendor_id')
                ->constrained('omsb_procurement_vendors')
                ->onDelete('cascade');
            
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_vq_document_number');
            $table->index('status', 'idx_vq_status');
            $table->index('quotation_date', 'idx_vq_quotation_date');
            $table->index('deleted_at', 'idx_vq_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_vendor_quotations');
    }
};
