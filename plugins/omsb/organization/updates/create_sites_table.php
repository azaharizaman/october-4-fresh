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
            $table->string('code')->unique('idx_sites_code_unique');
            $table->string('name');
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            
            // Foreign keys
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
                
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('omsb_organization_companies')
                ->nullOnDelete();
                
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('omsb_organization_addresses')
                ->nullOnDelete();
            
            $table->string('type')->default('Branch')->index('idx_sites_type');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('deleted_at', 'idx_sites_deleted_at');
            $table->index('address_id', 'idx_sites_address_id');
            $table->index('company_id', 'idx_sites_company_id');
            $table->index('parent_id', 'idx_sites_parent_id');
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
