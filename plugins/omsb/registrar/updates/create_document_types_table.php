<?php namespace Omsb\Registrar\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDocumentTypesTable Migration
 * 
 * Creates the document_types table for managing document numbering configuration.
 * Each document type defines its own numbering pattern, reset cycles, and protection rules.
 */
class CreateDocumentTypesTable extends Migration
{
    public function up()
    {
        Schema::create('omsb_registrar_document_types', function (Blueprint $table) {
            $table->id();
            
            // Basic identification
            $table->string('code', 20)->unique()->comment('Unique code for document type (e.g., PO, MRN, SA)');
            $table->string('name', 100)->comment('Human-readable name');
            $table->text('description')->nullable()->comment('Detailed description of document type');
            
            // Numbering configuration
            $table->string('numbering_pattern')->comment('Pattern template: {SITE}-{CODE}-{YYYY}-{######}');
            $table->string('prefix_template', 50)->nullable()->comment('Static or dynamic prefix');
            $table->string('suffix_template', 50)->nullable()->comment('Static or dynamic suffix');
            $table->enum('reset_cycle', ['never', 'yearly', 'monthly'])->default('yearly')->comment('When to reset numbering');
            $table->unsignedInteger('starting_number')->default(1)->comment('Starting sequence number');
            $table->unsignedInteger('current_number')->default(1)->comment('Current sequence number');
            $table->unsignedTinyInteger('number_length')->default(5)->comment('Zero-padded length of sequence');
            $table->unsignedTinyInteger('increment_by')->default(1)->comment('Increment amount per document');
            
            // Modifier support (for variants like appendix, spawn documents)
            $table->boolean('supports_modifiers')->default(false)->comment('Whether document supports modifiers like (A), (SBW)');
            $table->string('modifier_separator', 5)->nullable()->comment('Separator before modifier (e.g., "(", "-")');
            $table->json('modifier_options')->nullable()->comment('Available modifier options and descriptions');
            
            // Site and date requirements
            $table->boolean('requires_site_code')->default(true)->comment('Whether site code is required in numbering');
            $table->boolean('requires_year')->default(true)->comment('Whether year is required in numbering');
            $table->boolean('requires_month')->default(false)->comment('Whether month is required in numbering');
            
            // Document protection settings
            $table->string('protect_after_status', 50)->nullable()->comment('Status after which document cannot be edited');
            $table->json('void_only_statuses')->nullable()->comment('Statuses where only voiding is allowed');
            
            // Status and metadata
            $table->boolean('is_active')->default(true)->comment('Whether this document type is active');
            $table->unsignedInteger('created_by')->nullable()->comment('User who created this configuration');
            $table->unsignedInteger('updated_by')->nullable()->comment('User who last updated this configuration');
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreign('created_by')->references('id')->on('backend_users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('backend_users')->nullOnDelete();
            
            // Indexes
            $table->index(['code', 'is_active']);
            $table->index('is_active');
            $table->index('reset_cycle');
        });
    }

    public function down()
    {
        Schema::dropIfExists('omsb_registrar_document_types');
    }
}
