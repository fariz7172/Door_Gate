<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('departemen', function (Blueprint $table) {
            $table->increments('id');
            $table->string('number', 15)->default('')->index('DeptCode');
            $table->string('name', 30)->default('')->index('DeptName');
            $table->string('description', 100)->default('');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('departemen'); }
};
