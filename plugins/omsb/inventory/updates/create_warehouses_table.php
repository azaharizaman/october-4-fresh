<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateWarehousesTable Migration
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
        Schema::create('omsb_inventory_warehouses', function(Blueprint $table) {
            $table->id();
            $table->string('code')->unique('idx_warehouses_code_unique');
            $table->string('name');
            $table->string('status')->default('Active')->index('idx_warehouses_status');
            $table->string('type')->default('Main')->index('idx_warehouses_type');
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            
            // Foreign keys
            $table->foreignId('in_charge_person')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete()
                ->index('idx_warehouses_in_charge_person');
                
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete()
                ->index('idx_warehouses_site_id');
            
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('omsb_organization_addresses')
                ->nullOnDelete()
                ->index('idx_warehouses_address_id');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('deleted_at', 'idx_warehouses_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_warehouses');
    }
};
