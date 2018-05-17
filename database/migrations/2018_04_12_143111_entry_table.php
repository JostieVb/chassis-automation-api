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
            $table->string('caller', 50);
            $table->string('task_id', 50);
            $table->string('db_table', 50)->nullable();
            $table->integer('content_id')->nullable();
            $table->timestamp('date')->useCurrent();
            $table->string('label')->default('Not completed');
            $table->longText('response_message')->nullable();
            $table->string('status')->default('not-completed');
            $table->string('deleted', 5)->default('false');
            $table->string('unread', 5)->default('true');
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
