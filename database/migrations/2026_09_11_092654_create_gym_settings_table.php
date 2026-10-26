<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('gym_settings', function (Blueprint $table) {
            $table->id();
            $table->string('gym_name', 100)->default('FitQuarters Gym');
            $table->string('api_base_url', 255)->default('https://api.fitquarters.id');
            $table->string('api_key', 255)->nullable();
            $table->enum('relay_mode', ['http','mqtt','serial','dummy'])->default('dummy');
            $table->string('relay_ip', 100)->nullable()->comment('IP relay board e.g. 192.168.1.200');
            $table->string('default_device_id', 50)->default('front-gate-1');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('gym_settings'); }
};
