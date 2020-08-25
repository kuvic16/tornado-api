<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddWindColumnsIntoReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->integer('wind')->default(0)->after('mesh');
            $table->integer('rotation')->default(0)->after('mesh');
            $table->integer('windspeed')->default(0)->after('mesh');
            $table->integer('windmeasure')->default(0)->after('mesh');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('wind');
            $table->dropColumn('rotation');
            $table->dropColumn('windspeed');
            $table->dropColumn('windmeasure');
        });
    }
}
