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
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('tel_no')->nullable();
            $table->string('fax_no')->nullable();
            $table->string('type')->default('Branch');
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Parent site (self-referencing)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
            
            // Foreign key - Company relationship
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('omsb_organization_companies')
                ->cascadeOnDelete();
            
            // Foreign key - Address relationship
            $table->foreignId('address_id')
                ->nullable()
                ->constrained('omsb_organization_addresses')
                ->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_sites_code');
            $table->index('type', 'idx_sites_type');
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
