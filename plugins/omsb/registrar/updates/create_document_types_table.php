<?php namespace Omsb\Registrar\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateDocumentTypesTable Migration
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
        Schema::create('omsb_registrar_document_types', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('code')->unique(); // e.g., 'PR', 'PO', 'MRN', 'MRI'
            $table->string('name'); // e.g., 'Purchase Request', 'Purchase Order'
            $table->text('description')->nullable();
            $table->string('model_class'); // Fully qualified class name
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('code', 'idx_document_types_code');
            $table->index('is_active', 'idx_document_types_active');
            $table->index('deleted_at', 'idx_document_types_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_registrar_document_types');
    }
};
