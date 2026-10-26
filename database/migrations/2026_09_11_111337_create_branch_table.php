<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('branch', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('number')->nullable()->unique();
            $table->string('name', 30)->nullable();
            $table->string('description', 100)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('branch'); }
};
