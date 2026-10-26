<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        // access_logs
        try { DB::statement('CREATE INDEX idx_access_logs_scanned_at ON access_logs (scanned_at)'); } catch(\Throwable $e) {}
        try { DB::statement('CREATE INDEX idx_access_logs_status ON access_logs (status)'); } catch(\Throwable $e) {}
        try { DB::statement('CREATE INDEX idx_access_logs_member_id ON access_logs (member_id)'); } catch(\Throwable $e) {}

        // usersprofile
        try { DB::statement('CREATE INDEX idx_usersprofile_branchid ON usersprofile (Branchid)'); } catch(\Throwable $e) {}
        try { DB::statement('CREATE INDEX idx_usersprofile_depid ON usersprofile (Depid)'); } catch(\Throwable $e) {}
        try { DB::statement('CREATE INDEX idx_usersprofile_card ON usersprofile (Card)'); } catch(\Throwable $e) {}
    }
    public function down(): void {
        foreach([
            'idx_access_logs_scanned_at','idx_access_logs_status','idx_access_logs_member_id',
            'idx_usersprofile_branchid','idx_usersprofile_depid','idx_usersprofile_card',
        ] as $idx){
            try { DB::statement("DROP INDEX {$idx} ON access_logs"); } catch(\Throwable $e) {}
            try { DB::statement("DROP INDEX {$idx} ON usersprofile"); } catch(\Throwable $e) {}
        }
    }
};
