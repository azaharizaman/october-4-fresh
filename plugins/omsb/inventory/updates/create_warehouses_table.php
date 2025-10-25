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
            $table->string('status', 20)->default('active'); // active, inactive, maintenance
            $table->string('type', 30)->default('main'); // main, receiving, picking, quarantine
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            $table->boolean('is_receiving_warehouse')->default(false); // Default receiving for site
            $table->boolean('allows_negative_stock')->default(false);
            $table->text('description')->nullable();
            $table->decimal('storage_capacity', 15, 2)->nullable(); // Square meters or cubic meters
            $table->string('capacity_unit', 10)->nullable(); // sqm, cbm
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Site relationship (warehouses belong to sites)
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->cascadeOnDelete();
            
            // Foreign key - In charge person (warehouse manager)
            $table->foreignId('in_charge_person')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Address relationship (physical location)
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('omsb_organization_addresses')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_warehouses_code');
            $table->index('status', 'idx_warehouses_status');
            $table->index('type', 'idx_warehouses_type');
            $table->index('is_receiving_warehouse', 'idx_warehouses_receiving');
            $table->index('deleted_at', 'idx_warehouses_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_warehouses');
    }
};
