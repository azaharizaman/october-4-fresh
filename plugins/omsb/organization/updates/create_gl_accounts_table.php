<?php namespace Omsb\Organization\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateGlAccountsTable Migration
 * Chart of accounts entries at site level for financial tracking
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
        Schema::create('omsb_organization_gl_accounts', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            $table->string('account_code')->unique();
            $table->string('account_name');
            $table->text('description')->nullable();
            
            $table->enum('account_type', [
                'asset',
                'liability',
                'equity',
                'revenue',
                'expense',
                'contra'
            ]);
            
            $table->enum('account_subtype', [
                'current_asset',
                'fixed_asset',
                'current_liability',
                'long_term_liability',
                'capital',
                'retained_earnings',
                'operating_revenue',
                'non_operating_revenue',
                'operating_expense',
                'non_operating_expense',
                'contra_asset',
                'contra_revenue'
            ])->nullable();
            
            $table->boolean('is_active')->default(true);
            $table->boolean('is_header')->default(false); // Header accounts cannot have transactions
            $table->integer('level')->default(1); // Account hierarchy level
            
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign keys
            $table->foreignId('site_id')
                ->constrained('omsb_organization_sites')
                ->onDelete('cascade');
                
            $table->foreignId('parent_account_id')
                ->nullable()
                ->constrained('omsb_organization_gl_accounts')
                ->nullOnDelete();
            
            // Indexes
            $table->index('account_code', 'idx_gl_accounts_code');
            $table->index('account_type', 'idx_gl_accounts_type');
            $table->index('is_active', 'idx_gl_accounts_active');
            $table->index('deleted_at', 'idx_gl_accounts_deleted_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_organization_gl_accounts');
    }
};
