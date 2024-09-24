<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_stores_reviews_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStoresReviewsTable extends Migration
{
    public function up()
    {
        // Schema::create('stores_reviews', function (Blueprint $table) {
        //     $table->id();
        //     $table->unsignedBigInteger('user_id');
        //     $table->unsignedBigInteger('store_id');
        //     $table->unsignedBigInteger('module_id');
        //     $table->integer('rating')->check('rating >= 1 AND rating <= 5');
        //     $table->text('notes')->nullable();
        //     $table->timestamps();

        //     // Foreign key constraints (if necessary)
        //     $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        //     $table->foreign('store_id')->references('id')->on('stores')->onDelete('cascade');
        //     $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        // });
    }

    public function down()
    {
        // Schema::dropIfExists('stores_reviews');
    }
}
