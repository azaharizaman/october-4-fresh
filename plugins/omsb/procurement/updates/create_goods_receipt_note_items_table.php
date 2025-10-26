<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateGoodsReceiptNoteItemsTable Migration
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
        Schema::create('omsb_procurement_goods_receipt_note_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->integer('line_number');
            $table->text('item_description');
            $table->string('unit_of_measure');
            $table->decimal('quantity_ordered', 15, 2);
            $table->decimal('quantity_received', 15, 2);
            $table->decimal('quantity_rejected', 15, 2)->default(0);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreignId('goods_receipt_note_id')
                ->constrained('omsb_procurement_goods_receipt_notes')
                ->onDelete('cascade');
            
            $table->foreignId('purchase_order_item_id')
                ->constrained('omsb_procurement_purchase_order_items')
                ->onDelete('cascade');
            
            $table->foreignId('purchaseable_item_id')
                ->constrained('omsb_procurement_purchaseable_items')
                ->onDelete('cascade');
            
            // Indexes
            $table->index(['goods_receipt_note_id', 'line_number'], 'idx_grn_items_grn_line');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_goods_receipt_note_items');
    }
};
