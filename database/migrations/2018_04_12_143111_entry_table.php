<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EntryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('entry', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('recipient_id');
            $table->integer('sender_id');
            $table->integer('process_id');
            $table->string('caller');;
            $table->integer('task_id');
            $table->string('db_table');
            $table->integer('content_id');
            $table->timestamp('date');
            $table->string('label');
            $table->longText('response_message');
            $table->string('status');
            $table->string('deleted');
            $table->string('unread');
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
        Schema::dropIfExists('entry');
    }
}
