<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePurchaseRequestItemsTable Migration
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
        Schema::create('omsb_procurement_purchase_request_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->integer('line_number');
            $table->text('item_description');
            $table->string('unit_of_measure');
            $table->decimal('quantity_requested', 15, 2);
            $table->decimal('estimated_unit_cost', 15, 2)->nullable();
            $table->decimal('estimated_total_cost', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Foreign keys
            $table->foreignId('purchase_request_id')
                ->constrained('omsb_procurement_purchase_requests', 'id', 'fk_pr_items_pr')
                ->onDelete('cascade');
            
            $table->foreignId('purchaseable_item_id')
                ->nullable()
                ->constrained('omsb_procurement_purchaseable_items', 'id', 'fk_pr_items_purchaseable')
                ->nullOnDelete();
            
            // Indexes
            $table->index(['purchase_request_id', 'line_number'], 'idx_pr_items_pr_line');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_purchase_request_items');
    }
};
