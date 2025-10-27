<?php namespace Omsb\Registrar\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDocumentAuditTrailsTable Migration
 * 
 * Creates the audit trail table that logs every single action performed on documents.
 * Critical for compliance, fraud detection, and forensic analysis.
 */
class CreateDocumentAuditTrailsTable extends Migration
{
    public function up()
    {
        Schema::create('omsb_registrar_document_audit_trails', function (Blueprint $table) {
            $table->id();
            
            // Document reference
            $table->unsignedBigInteger('document_registry_id')->nullable()->comment('Link to document registry');
            $table->string('document_type_code', 20)->nullable()->comment('Document type for reporting');
            
            // Action details
            $table->string('action', 50)->comment('Action performed (create, update, lock, void, etc.)');
            $table->json('old_values')->nullable()->comment('Previous values before change');
            $table->json('new_values')->nullable()->comment('New values after change');
            $table->text('reason')->nullable()->comment('Reason for the action');
            
            // Audit metadata
            $table->string('ip_address', 45)->nullable()->comment('IP address of user performing action');
            $table->text('user_agent')->nullable()->comment('Browser/client information');
            $table->unsignedInteger('performed_by')->nullable()->comment('User who performed the action');
            $table->timestamp('performed_at')->comment('Exact timestamp of action');
            $table->json('metadata')->nullable()->comment('Additional context data');
            $table->timestamps();
            
            // Foreign keys
            $table->foreign('document_registry_id')->references('id')->on('omsb_registrar_document_registries')->cascadeOnDelete();
            $table->foreign('document_type_code')->references('code')->on('omsb_registrar_document_types')->nullOnDelete();
            $table->foreign('performed_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes for audit queries and compliance reporting
            $table->index(['document_registry_id', 'performed_at'], 'document_timeline');
            $table->index(['document_type_code', 'action', 'performed_at'], 'type_action_timeline');
            $table->index(['performed_by', 'performed_at'], 'user_activity_timeline');
            $table->index(['action', 'performed_at'], 'action_timeline');
            $table->index(['performed_at'], 'temporal_lookup');
            $table->index(['ip_address', 'performed_at'], 'ip_activity_tracking');
        });
    }

    public function down()
    {
        Schema::dropIfExists('omsb_registrar_document_audit_trails');
    }
}