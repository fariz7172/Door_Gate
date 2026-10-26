<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tbl_userlog', function (Blueprint $table) {
            $table->id('REC_INDEX'); // Membuat bigint AUTO_INCREMENT sebagai Primary Key
            $table->integer('USER_ADDR')->nullable();
            $table->dateTime('TM_EVENT')->nullable();
            $table->string('DEVICESN', 15)->nullable();
            $table->string('IMGPATH', 200)->nullable();
            $table->integer('devicetype')->nullable();
            $table->string('card', 15)->default('');
            $table->tinyInteger('stat')->default(0)->comment('0=Normal, 1=expired');
            $table->integer('flag')->default(0)->comment('0= Belum Send Data, 1= SudahSend Data');
            $table->string('scan_method', 10)->default('');
            
            // Unique index
            $table->unique(['USER_ADDR', 'TM_EVENT'], 'tbl_userlog_unique1');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tbl_userlog');
    }
};
