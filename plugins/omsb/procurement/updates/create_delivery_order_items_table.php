<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDeliveryOrderItemsTable Migration
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
        Schema::create('omsb_procurement_delivery_order_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->integer('line_number');
            $table->text('item_description');
            $table->string('unit_of_measure');
            $table->decimal('quantity_ordered', 15, 2);
            $table->decimal('quantity_delivered', 15, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreignId('delivery_order_id')
                ->constrained('omsb_procurement_delivery_orders', 'id', 'fk_do_items_do')
                ->onDelete('cascade');
            
            $table->foreignId('purchase_order_item_id')
                ->constrained('omsb_procurement_purchase_order_items', 'id', 'fk_do_items_po_item')
                ->onDelete('cascade');
            
            $table->foreignId('purchaseable_item_id')
                ->constrained('omsb_procurement_purchaseable_items', 'id', 'fk_do_items_purchaseable')
                ->onDelete('cascade');
            
            // Indexes
            $table->index(['delivery_order_id', 'line_number'], 'idx_do_items_do_line');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_delivery_order_items');
    }
};
