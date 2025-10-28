<?php

use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

/**
 * Test Model Migration
 * 
 * Creates test_models table for HasFeed trait unit testing.
 * This table is only used during test execution and is automatically
 * cleaned up after tests complete.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('test_models')) {
            return;
        }

        Schema::create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('status')->default('active');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('test_models');
    }
};
