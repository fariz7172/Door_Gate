<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('api_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('url', 255)->comment('URL endpoint e.g. http://localhost:787/sztimmy/opengate');
            $table->string('desc', 255)->nullable()->comment('Deskripsi fungsi e.g. Opengate');
            $table->string('group', 50)->default('general')->comment('soyal, tasoft, dreampos, system');
            $table->string('http_method', 10)->default('POST')->comment('GET, POST, PUT, DELETE');
            $table->text('default_payload')->nullable()->comment('JSON default body jika ada');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('api_endpoints'); }
};
