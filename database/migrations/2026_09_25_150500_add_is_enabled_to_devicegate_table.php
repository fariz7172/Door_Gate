<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 25 Sept 26 — status enable/disable mesin (setdevinfo disable_match)
        if (!Schema::hasColumn('devicegate', 'is_enabled')) {
            Schema::table('devicegate', function (Blueprint $table) {
                $table->tinyInteger('is_enabled')->default(1)->after('stat');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devicegate', 'is_enabled')) {
            Schema::table('devicegate', function (Blueprint $table) {
                $table->dropColumn('is_enabled');
            });
        }
    }
};
