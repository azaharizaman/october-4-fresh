<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateSitesTable Migration
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
        Schema::create('omsb_organization_sites', function(Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            $table->string('type')->default('Branch');
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Parent site (self-referencing)
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                ->references('id')
                ->on('omsb_organization_sites')
                ->onDelete('set null');
            
            // Foreign key - Company relationship
            $table->unsignedBigInteger('company_id')->nullable();
            $table->foreign('company_id')
                ->references('id')
                ->on('omsb_organization_companies')
                ->onDelete('cascade');
            
            // Foreign key - Address relationship
            $table->unsignedBigInteger('address_id')->nullable();
            $table->foreign('address_id')
                ->references('id')
                ->on('omsb_organization_addresses')
                ->onDelete('set null');
            
            // Indexes
            $table->index('code', 'idx_sites_code');
            $table->index('type', 'idx_sites_type');
            $table->index('parent_id', 'idx_sites_parent_id');
            $table->index('company_id', 'idx_sites_company_id');
            $table->index('address_id', 'idx_sites_address_id');
            $table->index('deleted_at', 'idx_sites_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_sites');
    }
};
