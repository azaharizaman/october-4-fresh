<?php namespace Omsb\Registrar\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDocumentRegistriesTable Migration
 * 
 * Creates the central document registry table that tracks ALL controlled documents
 * across the system. This is the core audit and anti-fraud table.
 */
class CreateDocumentRegistriesTable extends Migration
{
    public function up()
    {
        Schema::create('omsb_registrar_document_registries', function (Blueprint $table) {
            $table->id();
            
            // Document identification
            $table->string('document_number', 100)->comment('Core document number without site/modifiers');
            $table->string('document_type_code', 20)->comment('Reference to document type');
            $table->string('full_document_number')->unique()->comment('Complete formatted document number');
            
            // Location and temporal data
            $table->unsignedBigInteger('site_id')->nullable()->comment('Site where document was created');
            $table->string('site_code', 10)->nullable()->comment('Site code used in numbering');
            $table->unsignedSmallInteger('year')->comment('Year component of document number');
            $table->unsignedTinyInteger('month')->nullable()->comment('Month component (if used)');
            $table->unsignedInteger('sequence_number')->comment('Sequential number within period');
            
            // Modifier support
            $table->string('modifier', 10)->nullable()->comment('Document modifier (A, SBW, etc.)');
            
            // Document linkage (polymorphic)
            $table->string('documentable_type')->comment('Class of the actual document model');
            $table->unsignedBigInteger('documentable_id')->comment('ID of the actual document');
            
            // Status tracking
            $table->string('status', 50)->comment('Current document status');
            $table->string('previous_status', 50)->nullable()->comment('Previous status for audit trail');
            
            // Protection mechanisms
            $table->boolean('is_locked')->default(false)->comment('Whether document is locked from editing');
            $table->timestamp('locked_at')->nullable()->comment('When document was locked');
            $table->unsignedInteger('locked_by')->nullable()->comment('User who locked the document');
            $table->text('lock_reason')->nullable()->comment('Reason for locking');
            
            // Void handling
            $table->boolean('is_voided')->default(false)->comment('Whether document is voided');
            $table->timestamp('voided_at')->nullable()->comment('When document was voided');
            $table->unsignedInteger('voided_by')->nullable()->comment('User who voided the document');
            $table->text('void_reason')->nullable()->comment('Reason for voiding');
            
            // Audit metadata
            $table->unsignedInteger('created_by')->nullable()->comment('User who created the document');
            $table->unsignedInteger('updated_by')->nullable()->comment('User who last updated the document');
            $table->json('metadata')->nullable()->comment('Additional audit metadata (IP, user agent, etc.)');
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('document_type_code', 'fk_registry_doc_type')
                ->references('code')
                ->on('omsb_registrar_document_types')
                ->restrictOnDelete();
            $table->foreign('site_id', 'fk_registry_site')
                ->references('id')
                ->on('omsb_organization_sites')
                ->nullOnDelete();
            $table->foreign('created_by', 'fk_registry_creator')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_registry_updater')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
            $table->foreign('locked_by', 'fk_registry_locker')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
            $table->foreign('voided_by', 'fk_registry_voider')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
            
            // Critical indexes for performance and fraud detection
            $table->unique(['documentable_type', 'documentable_id'], 'unique_document_linkage');
            $table->index(['document_type_code', 'year', 'month', 'sequence_number'], 'sequence_integrity');
            $table->index(['document_type_code', 'site_id', 'year'], 'site_year_lookup');
            $table->index(['full_document_number'], 'document_number_lookup');
            $table->index(['status', 'is_voided', 'is_locked'], 'status_protection_lookup');
            $table->index(['created_at', 'document_type_code'], 'temporal_type_lookup');
            $table->index('is_voided');
            $table->index('is_locked');
        });
    }

    public function down()
    {
        Schema::dropIfExists('omsb_registrar_document_registries');
    }
}