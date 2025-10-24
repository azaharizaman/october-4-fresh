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
            
            // Foreign key - Company relationship
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')
                ->references('id')
                ->on('omsb_organization_companies')
                ->onDelete('cascade');
            
            // Indexes
            $table->index('company_id', 'idx_addresses_company_id');
            $table->index('deleted_at', 'idx_addresses_deleted_at');
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