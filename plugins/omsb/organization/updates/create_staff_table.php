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
            $table->string('staff_number')->nullable()->unique();
            $table->boolean('is_manager')->nullable();
            $table->date('date_join')->nullable();
            $table->date('date_resigned')->nullable();
            $table->string('position')->nullable();
            $table->string('qualification')->nullable();
            $table->string('contact_no')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Backend user relationship
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('backend_users')
                ->onDelete('set null');
            
            // Foreign key - Site relationship
            $table->unsignedBigInteger('site_id')->nullable();
            $table->foreign('site_id')
                ->references('id')
                ->on('omsb_organization_sites')
                ->onDelete('set null');
            
            // Foreign key - Company relationship
            $table->unsignedBigInteger('company_id')->nullable()->default(1);
            $table->foreign('company_id')
                ->references('id')
                ->on('omsb_organization_companies')
                ->onDelete('set null');
            
            // Indexes
            $table->index('staff_number', 'idx_staff_staff_number');
            $table->index('user_id', 'idx_staff_user_id');
            $table->index('site_id', 'idx_staff_site_id');
            $table->index('company_id', 'idx_staff_company_id');
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
