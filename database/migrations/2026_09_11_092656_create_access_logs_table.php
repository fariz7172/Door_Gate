<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('door_id')->nullable()->constrained('doors')->nullOnDelete();
            $table->string('member_id', 100)->comment('barcode / FitQuarters member id');
            $table->string('member_name', 150)->nullable();
            $table->enum('scan_type', ['check_in','check_out'])->default('check_in');
            $table->enum('status', ['granted','denied','error'])->default('granted');
            $table->string('reason', 255)->nullable();
            $table->string('fitquarters_event_id', 100)->nullable();
            $table->dateTime('scanned_at')->useCurrent();
            $table->timestamps();
            $table->index(['status','scanned_at']);
            $table->index('member_id');
        });
    }
    public function down(): void { Schema::dropIfExists('access_logs'); }
};
