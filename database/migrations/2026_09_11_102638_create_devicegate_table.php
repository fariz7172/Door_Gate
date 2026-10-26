<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Keep doors table for backward compat, add devicegate alongside
        Schema::create('devicegate', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 255)->nullable();
            $table->integer('number')->default(0);
            $table->integer('flagstatus')->default(0)->comment('0 = disconnect 1= connect');
            $table->integer('type')->nullable()->comment('type 0 Finger, 50 face, 1 soyal');
            $table->string('sn', 15)->nullable();
            $table->string('ip', 20)->nullable();
            $table->integer('nodeid')->default(0)->comment('nodeid soyal');
            $table->string('description', 500)->nullable()->comment('untuk keterangan');
            $table->integer('groupid')->default(0);
            $table->tinyInteger('stat')->default(0)->comment('0=IN, 1=Out');
            $table->unsignedTinyInteger('Lift1')->default(0)->comment('Setting Lift 1- 16');
            $table->unsignedTinyInteger('Lift2')->default(0)->comment('Setting Lift 17- 32');
            $table->unsignedTinyInteger('Lift3')->default(0)->comment('Setting Lift 33- 48');
            $table->unsignedTinyInteger('Lift4')->default(0)->comment('Setting Lift 49- 64');
            $table->integer('locationid')->default(0);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('devicegate'); }
};
