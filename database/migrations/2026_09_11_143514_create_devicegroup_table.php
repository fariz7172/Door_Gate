<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('devicegroup', function (Blueprint $table) {
            $table->id();
            $table->string('number', 11)->nullable();
            $table->string('name', 30)->nullable();
            $table->string('description', 1000)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('devicegroup'); }
};
