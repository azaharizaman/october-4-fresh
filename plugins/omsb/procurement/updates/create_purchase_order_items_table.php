<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePurchaseOrderItemsTable Migration
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
        Schema::create('omsb_procurement_purchase_order_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->integer('line_number');
            $table->text('item_description');
            $table->string('unit_of_measure');
            $table->decimal('quantity_ordered', 15, 2);
            $table->decimal('quantity_received', 15, 2)->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_percentage', 5, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('line_total', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreignId('purchase_order_id')
                ->constrained('omsb_procurement_purchase_orders', 'id', 'fk_po_items_po')
                ->onDelete('cascade');
            
            $table->foreignId('purchaseable_item_id')
                ->constrained('omsb_procurement_purchaseable_items', 'id', 'fk_po_items_purchaseable')
                ->onDelete('cascade');
            
            // Target site for this line item (can be different per line)
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites', 'id', 'fk_po_items_site')
                ->onDelete('cascade');
            
            $table->foreignId('vendor_quotation_item_id')
                ->nullable()
                ->constrained('omsb_procurement_vendor_quotation_items', 'id', 'fk_po_items_vq_item')
                ->nullOnDelete();
            
            // Indexes
            $table->index(['purchase_order_id', 'line_number'], 'idx_po_items_po_line');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_purchase_order_items');
    }
};
