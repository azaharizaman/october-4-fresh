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
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('status')->default('Active');
            $table->string('type')->default('Main');
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - In charge person
            $table->foreignId('in_charge_person')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Site relationship
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
            
            // Foreign key - Address relationship
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('omsb_organization_addresses')
                ->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_warehouses_code');
            $table->index('status', 'idx_warehouses_status');
            $table->index('type', 'idx_warehouses_type');
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
