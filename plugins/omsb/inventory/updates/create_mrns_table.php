<?php namespace Omsb\Inventory\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateMrnsTable Migration
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
        Schema::create('omsb_inventory_mrns', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('mrn_number')->unique(); // Document number from Registrar
            $table->string('document_number', 120)->nullable(); // Controlled document number
            $table->date('received_date');
            $table->string('delivery_note_number')->nullable(); // Vendor's delivery note
            $table->string('vehicle_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->timestamp('received_time')->nullable();
            $table->text('remarks')->nullable();
            $table->decimal('total_received_value', 15, 2)->default(0); // Total value of goods received
            $table->string('status', 20)->default('draft'); // draft, submitted, approved, completed
            $table->string('previous_status', 50)->nullable(); // Track previous status for audit
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key - Warehouse (where goods are received)
            $table->foreignId('warehouse_id')
                ->constrained('omsb_inventory_warehouses')
                ->restrictOnDelete();
            
            // Foreign key - Document Registry (for controlled document tracking)
            $table->foreignId('registry_id')
                ->nullable()
                ->constrained('omsb_registrar_document_registries')
                ->nullOnDelete();
                
            // Foreign key - Source document (typically Goods Receipt Note from Procurement)
            // NOTE: This FK references Procurement plugin - needs to be created there first
            $table->foreignId('goods_receipt_note_id')
                ->nullable()
                ->constrained('omsb_procurement_goods_receipt_notes')
                ->nullOnDelete();
            
            // Foreign key - Received by staff
            // NOTE: This FK references Organization plugin - needs to be created there first  
            $table->foreignId('received_by')
                ->constrained('omsb_organization_staff')
                ->restrictOnDelete();
                
            // Foreign key - Approved by staff
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('omsb_organization_staff')
                ->nullOnDelete();
            
            // Foreign key - Created by user (backend_users)
            $table->unsignedInteger('created_by');
            $table->foreign('created_by')->references('id')->on('backend_users')->restrictOnDelete();
            
            // Indexes
            $table->index('mrn_number', 'idx_mrn_number');
            $table->index('document_number', 'idx_mrn_document_number');
            $table->index('received_date', 'idx_mrn_received_date');
            $table->index('status', 'idx_mrn_status');
            $table->index('deleted_at', 'idx_mrn_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_inventory_mrns');
    }
};
