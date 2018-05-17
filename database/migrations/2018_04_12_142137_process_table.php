<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ProcessTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('process', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 50);
            $table->string('name', 200);
            $table->longText('process_xml')->nullable();
            $table->longText('process_json')->nullable();
            $table->longText('properties')->nullable();
            $table->string('deploy', 5)->default('false');
            $table->string('deleted', 5)->default('false');
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
        Schema::dropIfExists('process');
    }
}
