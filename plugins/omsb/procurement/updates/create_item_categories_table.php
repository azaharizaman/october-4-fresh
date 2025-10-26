<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateItemCategoriesTable Migration
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
        Schema::create('omsb_procurement_item_categories', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Self-referencing for hierarchical categories
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('omsb_procurement_item_categories')
                ->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_item_categories_code');
            $table->index('is_active', 'idx_item_categories_active');
            $table->index('deleted_at', 'idx_item_categories_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_item_categories');
    }
};
