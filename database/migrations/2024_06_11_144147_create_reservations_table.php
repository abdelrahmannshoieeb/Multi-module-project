<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReservationsTable extends Migration
{
    public function up()
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // Reference to the users table
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->integer('guest_numbers');
            $table->date('reservation_date');
            $table->time('reservation_time');
            $table->text('notes')->nullable();
            $table->enum('where_to_seat', ['inside', 'outside', 'bar', 'sidebar', 'NonSmoker', 'Other']);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('reservations');
    }
}