<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sync_gate_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('employee_id', 50)->nullable()->index();
            $table->string('name', 100)->nullable();
            $table->string('card', 30)->nullable();
            $table->string('sn', 50)->index();
            $table->string('endpoint', 50); // 'registerface' atau 'useraccess'
            $table->string('status', 20)->index(); // 'SUCCESS', 'FAILED', 'SKIPPED'
            $table->integer('http_status')->nullable();
            $table->text('message')->nullable();
            $table->json('payload_summary')->nullable();
            $table->timestamp('synced_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sync_gate_logs');
    }
};
