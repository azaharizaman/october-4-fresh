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
            
            // Foreign key
            $table->foreign('company_id')
                ->references('id')->on('omsb_organization_companies')
                ->onDelete('null')
                ->index('idx_addresses_company_id');
            
            // Address usage types (boolean flags)
            $table->boolean('is_mailing')->default(false);
            $table->boolean('is_administrative')->default(false);
            $table->boolean('is_receiving_goods')->default(false); // For PO deliveries
            $table->boolean('is_billing')->default(false);
            $table->boolean('is_registered_office')->default(false); // Legal registered address
            $table->boolean('is_primary')->default(false); // Primary address for the company
            
            // Additional attributes
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable(); // Additional notes about this address usage
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'is_primary'], 'idx_addresses_company_primary');
            $table->index(['company_id', 'is_mailing'], 'idx_addresses_company_mailing');
            $table->index(['company_id', 'is_receiving_goods'], 'idx_addresses_company_receiving');
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