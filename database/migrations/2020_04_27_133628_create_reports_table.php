<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('report_type', 50);
            $table->string('object_id', 250)->nullable();
            $table->string('unix_timestamp', 20)->nullable();

            // storm reports
            $table->string('event', 20)->nullable();
            $table->double('latitude')->nullable();
            $table->double('longitude')->nullable();
            $table->string('magnitude', 20)->nullable();
            $table->string('city', 250)->nullable();
            $table->string('county', 250)->nullable();
            $table->string('state', 250)->nullable();
            $table->string('source', 250)->nullable();

            // spotter network
            $table->integer('tornado')->default(0);
            $table->integer('funnelcloud')->default(0);
            $table->integer('hail')->default(0);
            $table->integer('hailsize')->default(0);

            // tornado warning
            $table->string('phenom', 250)->nullable();
            $table->string('significance', 250)->nullable();
            $table->string('office', 250)->nullable();
            $table->string('office_id', 250)->nullable();
            $table->text('latlon')->nullable();

            // cimms
            $table->integer('prob_hail')->default(0);
            $table->integer('prob_wind')->default(0);
            $table->integer('prob_tor')->default(0);

            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('reports');
    }
}
