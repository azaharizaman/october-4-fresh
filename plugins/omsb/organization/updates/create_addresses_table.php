<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateAddressesTable Migration
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
        Schema::create('omsb_organization_addresses', function(Blueprint $table) {
            $table->id();
            $table->text('address_street')->nullable();
            $table->string('address_city')->nullable();
            $table->string('address_state')->default('Sarawak');
            $table->string('address_postcode')->nullable();
            $table->string('address_country')->default('Malaysia');
            $table->string('region')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign Key column without constraint to avoid circular dependency
            // The relationship is managed at the ORM level in the Address model
            $table->unsignedBigInteger('company_id')->nullable();
            
            // Unique constraint
            $table->unique(['address_street', 'address_city', 'address_state', 'address_postcode', 'address_country'], 'uniq_addresses');
            
            // Indexes
            $table->index('deleted_at', 'idx_addresses_deleted_at');
            $table->index('company_id', 'idx_addresses_company_id');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_addresses');
    }
};