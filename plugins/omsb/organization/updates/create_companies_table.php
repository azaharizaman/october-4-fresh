<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateCompaniesTable Migration
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
        Schema::create('omsb_organization_companies', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Parent company (self-referencing)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('omsb_organization_companies')
                ->nullOnDelete();
            
            // Indexes
            $table->index('code', 'idx_companies_code');
            $table->index('deleted_at', 'idx_companies_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_companies');
    }
};
