<?php namespace Omsb\Budget\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * AddControlledDocumentFieldsToBudgetTransactions Migration
 * 
 * Adds fields required by HasFinancialDocumentProtection trait
 * These fields support document registry, voiding, and audit capabilities
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
        // Add fields to budget_transfers table
        Schema::table('omsb_budget_transfers', function(Blueprint $table) {
            $table->foreignId('registry_id')->nullable()->after('id')
                ->constrained('omsb_registrar_document_registry')
                ->nullOnDelete();
                
            $table->boolean('is_voided')->default(false)->after('status');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->unsignedInteger('voided_by')->nullable()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->string('previous_status', 50)->nullable()->after('void_reason');
            
            $table->foreign('voided_by')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
                
            $table->index('is_voided', 'idx_budget_transfers_voided');
        });

        // Add fields to budget_adjustments table
        Schema::table('omsb_budget_adjustments', function(Blueprint $table) {
            $table->foreignId('registry_id')->nullable()->after('id')
                ->constrained('omsb_registrar_document_registry')
                ->nullOnDelete();
                
            $table->boolean('is_voided')->default(false)->after('status');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->unsignedInteger('voided_by')->nullable()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->string('previous_status', 50)->nullable()->after('void_reason');
            
            $table->foreign('voided_by')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
                
            $table->index('is_voided', 'idx_budget_adjustments_voided');
        });

        // Add fields to budget_reallocations table
        Schema::table('omsb_budget_reallocations', function(Blueprint $table) {
            $table->foreignId('registry_id')->nullable()->after('id')
                ->constrained('omsb_registrar_document_registry')
                ->nullOnDelete();
                
            $table->boolean('is_voided')->default(false)->after('status');
            $table->timestamp('voided_at')->nullable()->after('is_voided');
            $table->unsignedInteger('voided_by')->nullable()->after('voided_at');
            $table->text('void_reason')->nullable()->after('voided_by');
            $table->string('previous_status', 50)->nullable()->after('void_reason');
            
            $table->foreign('voided_by')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
                
            $table->index('is_voided', 'idx_budget_reallocations_voided');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::table('omsb_budget_transfers', function(Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['registry_id']);
            $table->dropIndex('idx_budget_transfers_voided');
            $table->dropColumn([
                'registry_id',
                'is_voided',
                'voided_at',
                'voided_by',
                'void_reason',
                'previous_status'
            ]);
        });

        Schema::table('omsb_budget_adjustments', function(Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['registry_id']);
            $table->dropIndex('idx_budget_adjustments_voided');
            $table->dropColumn([
                'registry_id',
                'is_voided',
                'voided_at',
                'voided_by',
                'void_reason',
                'previous_status'
            ]);
        });

        Schema::table('omsb_budget_reallocations', function(Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['registry_id']);
            $table->dropIndex('idx_budget_reallocations_voided');
            $table->dropColumn([
                'registry_id',
                'is_voided',
                'voided_at',
                'voided_by',
                'void_reason',
                'previous_status'
            ]);
        });
    }
};
