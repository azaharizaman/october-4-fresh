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
            $table->engine = 'InnoDB';
            
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
            $table->integer('user_id')->nullable()->unsigned();
            $table->foreign('user_id')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
            
  
            // Foreign key - Site relationship
                $table->foreignId('site_id')
                    ->nullable()
                    ->constrained('omsb_organization_sites')
                    ->nullOnDelete();
            
            // Foreign key - Company relationship
            $table->foreignId('company_id')
                ->nullable()
                ->default(1)
                ->constrained('omsb_organization_companies')
                ->nullOnDelete();
            
            // Service code for department/service assignment
            $table->string('service_code', 10)->nullable();
            
            // Indexes
            $table->index('staff_number', 'idx_staff_staff_number');
            $table->index('service_code');
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
