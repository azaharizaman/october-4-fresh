<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePurchaseableItemsTable Migration
 * Master catalog of all items that can be purchased
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
        Schema::create('omsb_procurement_purchaseable_items', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('barcode')->nullable()->unique();
            $table->string('unit_of_measure'); // e.g., 'each', 'box', 'kg', 'litre'
            
            // Item classification
            $table->boolean('is_inventory_item')->default(false);
            $table->enum('item_type', [
                'consumable', 
                'equipment', 
                'spare_part', 
                'asset',
                'service',
                'other'
            ])->default('consumable');
            
            // Pricing
            $table->decimal('standard_cost', 15, 2)->nullable();
            $table->decimal('last_purchase_cost', 15, 2)->nullable();
            $table->date('last_purchase_date')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_discontinued')->default(false);
            
            // Additional metadata
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->text('specifications')->nullable();
            $table->integer('lead_time_days')->nullable(); // Average lead time
            $table->integer('minimum_order_quantity')->default(1);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('item_category_id')
                ->nullable()
                ->constrained('omsb_procurement_item_categories')
                ->nullOnDelete();
                
            $table->unsignedInteger('gl_account_id')->nullable();
            $table->foreign('gl_account_id')
                ->references('id')
                ->on('omsb_organization_gl_accounts')
                ->nullOnDelete();
            
            $table->foreignId('preferred_vendor_id')
                ->nullable()
                ->constrained('omsb_procurement_vendors')
                ->nullOnDelete();
            
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_purchaseable_items_code');
            $table->index('is_inventory_item', 'idx_purchaseable_items_inventory');
            $table->index('item_type', 'idx_purchaseable_items_type');
            $table->index('is_active', 'idx_purchaseable_items_active');
            $table->index('deleted_at', 'idx_purchaseable_items_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_purchaseable_items');
    }
};
