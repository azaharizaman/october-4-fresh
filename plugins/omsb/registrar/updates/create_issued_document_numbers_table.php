<?php namespace Omsb\Registrar\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateIssuedDocumentNumbersTable Migration
 * Tracks all issued document numbers to avoid duplication
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
        Schema::create('omsb_registrar_issued_document_numbers', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('document_number')->unique();
            $table->date('issued_date');
            
            // Polymorphic relationship to document
            $table->string('documentable_type');
            $table->unsignedBigInteger('documentable_id');
            
            $table->enum('status', [
                'active',
                'cancelled',
                'voided'
            ])->default('active');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('document_type_id')
                ->constrained('omsb_registrar_document_types')
                ->onDelete('cascade');
            
            $table->foreignId('document_number_pattern_id')
                ->constrained('omsb_registrar_document_number_patterns')
                ->onDelete('cascade');
            
            $table->unsignedInteger('issued_by')->nullable();
            $table->foreign('issued_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index('document_number', 'idx_issued_docs_number');
            $table->index(['documentable_type', 'documentable_id'], 'idx_issued_docs_documentable');
            $table->index('issued_date', 'idx_issued_docs_date');
            $table->index('status', 'idx_issued_docs_status');
            $table->index('deleted_at', 'idx_issued_docs_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_registrar_issued_document_numbers');
    }
};
