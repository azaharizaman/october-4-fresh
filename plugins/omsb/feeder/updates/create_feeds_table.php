<?php namespace Omsb\Feeder\Updates;

use Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * CreateFeedsTable Migration
 *
 * Creates the feeds table for tracking user activities across the system
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
        Schema::create('omsb_feeder_feeds', function(Blueprint $table) {
            $table->engine = 'InnoDB';
            
            $table->id();
            
            // Action type (create, update, delete, approve, reject, etc.)
            $table->string('action_type', 50)->index();
            
            // Polymorphic relationship to any model
            $table->string('feedable_type', 255)->index();
            $table->unsignedBigInteger('feedable_id')->index();
            
            // Title and body for notes/comments
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            
            // Additional data as JSON
            $table->json('additional_data')->nullable();
            
            $table->timestamps();
            
            // Foreign key - Backend user relationship
            $table->unsignedInteger('user_id')->nullable();
            $table->foreign('user_id')
                ->references('id')
                ->on('backend_users')
                ->nullOnDelete();
            
            // Indexes for common queries
            $table->index(['feedable_type', 'feedable_id'], 'idx_feeds_feedable');
            $table->index('action_type', 'idx_feeds_action_type');
            $table->index('user_id', 'idx_feeds_user_id');
            $table->index('created_at', 'idx_feeds_created_at');
        });
    }

    /**
     * down reverses the migration
     */
    public function down()
    {
        Schema::dropIfExists('omsb_feeder_feeds');
    }
};
