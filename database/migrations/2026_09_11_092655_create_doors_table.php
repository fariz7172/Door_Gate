<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('doors', function (Blueprint $table) {
            $table->id();
            $table->string('door_code', 50)->unique()->comment('slug: front-gate-1');
            $table->string('name', 100)->comment('Pintu Utama Masuk');
            $table->enum('direction', ['in','out','bidirectional'])->default('bidirectional');
            $table->unsignedTinyInteger('relay_channel')->default(1);
            $table->unsignedInteger('pulse_duration_ms')->default(3000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('is_active');
        });
    }
    public function down(): void { Schema::dropIfExists('doors'); }
};
