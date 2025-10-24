<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateCompanyAddressesTable Migration
 * Pivot table for Company-Address many-to-many relationship with additional attributes
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
        Schema::create('omsb_organization_company_addresses', function(Blueprint $table) {
            $table->id();
            
            // Foreign keys for the many-to-many relationship
            $table->foreignId('company_id')
                ->constrained('omsb_organization_companies')
                ->cascadeOnDelete();
                
            $table->foreignId('address_id')
                ->constrained('omsb_organization_addresses')
                ->cascadeOnDelete();
            
            // Address usage types (can have multiple purposes)
            $table->boolean('is_mailing')->default(false);
            $table->boolean('is_administrative')->default(false);
            $table->boolean('is_receiving_goods')->default(false); // For PO deliveries
            $table->boolean('is_billing')->default(false);
            $table->boolean('is_registered_office')->default(false); // Legal registered address
            
            // Additional pivot attributes
            $table->boolean('is_primary')->default(false); // Primary address for the company
            $table->boolean('is_active')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable(); // Additional notes about this address usage
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['company_id', 'is_primary'], 'idx_company_addresses_company_primary');
            $table->index(['company_id', 'is_mailing'], 'idx_company_addresses_company_mailing');
            $table->index(['company_id', 'is_receiving_goods'], 'idx_company_addresses_company_receiving');
            $table->index('deleted_at', 'idx_company_addresses_deleted_at');
            $table->index('address_id', 'idx_company_addresses_address_id');
            $table->index('company_id', 'idx_company_addresses_company_id');
            
            // Unique constraint to prevent duplicate company-address combinations
            $table->unique(['company_id', 'address_id', 'deleted_at'], 'idx_company_addresses_unique');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_company_addresses');
    }
};