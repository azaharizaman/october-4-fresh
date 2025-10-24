<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateStaffTable Migration
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
        Schema::create('omsb_organization_staff', function(Blueprint $table) {
            $table->id();
            $table->string('staff_number')->nullable()->unique('idx_staff_staff_number_unique');
            $table->boolean('is_manager')->nullable();
            $table->date('date_join')->nullable();
            $table->date('date_resigned')->nullable();
            $table->string('position')->nullable();
            $table->string('qualification')->nullable();
            $table->string('contact_no')->nullable();
            
            // Foreign keys
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('backend_users')
                ->nullOnDelete()
                ->index('idx_staff_user_id');
                
            $table->foreignId('unit_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete()
                ->index('idx_staff_unit_id');
                
            $table->foreignId('company_id')
                ->nullable()
                ->default(1)
                ->constrained('omsb_organization_companies')
                ->nullOnDelete()
                ->index('idx_staff_company_id');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('deleted_at', 'idx_staff_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_staff');
    }
};
