<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAppointmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Schema::create('appointments', function (Blueprint $table) {
        //     $table->id();
        //     $table->json('booked_features');
        //     $table->integer('number_of_patients');
        //     $table->date('date');
        //     $table->time('time');
        //     $table->string('full_name');
        //     $table->string('phone');
        //     $table->string('email');
        //     $table->string('payment_type');
        //     $table->integer('total_orders');
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Schema::dropIfExists('appointments');
    }
}
