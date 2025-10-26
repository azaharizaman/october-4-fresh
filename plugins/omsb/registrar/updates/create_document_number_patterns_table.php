<?php namespace Omsb\Registrar\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDocumentNumberPatternsTable Migration
 * Defines numbering patterns per document type
 * Default pattern: SITECODE-DOCUMENTCODE-YYYY-#####
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
        Schema::create('omsb_registrar_document_number_patterns', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('pattern'); // e.g., '{SITE}-{DOCTYPE}-{YYYY}-{#####}'
            $table->string('prefix')->nullable(); // Optional fixed prefix
            $table->string('suffix')->nullable(); // Optional fixed suffix
            $table->enum('reset_interval', ['never', 'yearly', 'monthly'])->default('yearly');
            $table->integer('next_number')->default(1);
            $table->integer('number_length')->default(5); // Padding length for running number
            $table->integer('current_year')->nullable(); // Track year for yearly reset
            $table->integer('current_month')->nullable(); // Track month for monthly reset
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('document_type_id')
                ->constrained('omsb_registrar_document_types')
                ->onDelete('cascade');
            
            $table->foreignId('site_id')
                ->nullable()
                ->constrained('omsb_organization_sites')
                ->nullOnDelete();
            
            // Indexes
            $table->index(['document_type_id', 'site_id'], 'idx_doc_patterns_type_site');
            $table->index('is_active', 'idx_doc_patterns_active');
            $table->index('deleted_at', 'idx_doc_patterns_deleted_at');
            
            // Unique constraint: one pattern per document type per site
            $table->unique(['document_type_id', 'site_id'], 'uq_doc_patterns_type_site');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_registrar_document_number_patterns');
    }
};
