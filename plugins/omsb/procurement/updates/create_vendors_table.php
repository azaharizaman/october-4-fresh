<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateVendorsTable Migration
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
        Schema::create('omsb_procurement_vendors', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('registration_number')->nullable();
            $table->date('incorporation_date')->nullable();
            $table->string('sap_code')->nullable();
            
            // Vendor classification
            $table->boolean('is_bumi')->default(false);
            $table->string('type')->nullable(); // Standard, Contractor, Standard Vendor, Specialized Vendor, etc.
            $table->string('category')->nullable();
            $table->boolean('is_specialized')->default(false);
            $table->boolean('is_precision')->default(false);
            $table->boolean('is_approved')->default(false);
            
            // Tax/GST information
            $table->boolean('is_gst')->default(false);
            $table->string('gst_number')->nullable();
            $table->string('gst_type')->nullable();
            $table->string('tax_number')->nullable(); // Keep for backward compatibility
            
            // Country/origin information
            $table->boolean('is_foreign')->default(false);
            $table->unsignedInteger('country_id')->nullable();
            $table->unsignedInteger('origin_country_id')->nullable();
            
            // Contact information
            $table->string('contact_person')->nullable();
            $table->string('designation')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            $table->string('hp_no')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            
            // Address fields
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->unsignedInteger('state_id')->nullable();
            $table->string('postcode')->nullable();
            
            // Business information
            $table->text('scope_of_work')->nullable();
            $table->string('service')->nullable();
            
            // Credit management
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('credit_terms')->nullable();
            $table->timestamp('credit_updated_at')->nullable();
            $table->string('credit_review')->nullable();
            $table->text('credit_remarks')->nullable();
            
            // Status and payment
            $table->string('status')->default('Active');
            $table->enum('payment_terms', ['cod', 'net_15', 'net_30', 'net_45', 'net_60', 'net_90'])->default('net_30');
            $table->text('notes')->nullable();
            
            // Company association (for multi-company setup)
            $table->unsignedInteger('company_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->unsignedBigInteger('address_id')->nullable();
            $table->foreign('address_id')->references('id')->on('omsb_organization_addresses')->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_vendors_code');
            $table->index('status', 'idx_vendors_status');
            $table->index('type', 'idx_vendors_type');
            $table->index('is_bumi', 'idx_vendors_is_bumi');
            $table->index('is_specialized', 'idx_vendors_is_specialized');
            $table->index('is_approved', 'idx_vendors_is_approved');
            $table->index('company_id', 'idx_vendors_company_id');
            $table->index('deleted_at', 'idx_vendors_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_vendors');
    }
};
