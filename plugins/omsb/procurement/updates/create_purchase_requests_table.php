<?php namespace Omsb\Procurement\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreatePurchaseRequestsTable Migration
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
        Schema::create('omsb_procurement_purchase_requests', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique();
            $table->date('request_date');
            $table->date('required_date');
            
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->enum('status', [
                'draft',
                'submitted',
                'reviewed',
                'approved',
                'rejected',
                'cancelled',
                'completed'
            ])->default('draft');
            
            $table->string('purpose');
            $table->text('justification')->nullable();
            $table->text('notes')->nullable();
            
            $table->decimal('total_amount', 15, 2)->default(0);
            
            // Approval tracking
            $table->unsignedInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->onDelete('cascade');
            
            $table->foreignId('requested_by')
                ->constrained('omsb_organization_staff')
                ->onDelete('cascade');
            
            // Service code for department/service assignment
            $table->string('service_code', 10)->nullable();
            
            $table->unsignedInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_pr_document_number');
            $table->index('service_code');
            $table->index('status', 'idx_pr_status');
            $table->index('request_date', 'idx_pr_request_date');
            $table->index('deleted_at', 'idx_pr_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_procurement_purchase_requests');
    }
};
