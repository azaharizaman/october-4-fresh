<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateVendorShareholdersTable Migration
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
        Schema::create('omsb_procurement_vendor_shareholders', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            
            // Foreign key to vendor
            $table->foreignId('vendor_id')
                ->constrained('omsb_procurement_vendors', 'id', 'fk_shareholders_vendor')
                ->onDelete('cascade');
            
            // Shareholder information
            $table->string('name');
            $table->string('ic_no')->nullable(); // IC/Passport number
            $table->string('designation')->nullable(); // Position/role
            $table->string('category')->nullable(); // Category type
            $table->string('share')->nullable(); // Share percentage or amount
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('vendor_id', 'idx_shareholders_vendor_id');
            $table->index('name', 'idx_shareholders_name');
            $table->index('ic_no', 'idx_shareholders_ic_no');
            $table->index('deleted_at', 'idx_shareholders_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_vendor_shareholders');
    }
};
