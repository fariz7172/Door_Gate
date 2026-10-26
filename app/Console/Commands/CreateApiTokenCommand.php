<?php
namespace App\Console\Commands;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateApiTokenCommand extends Command
{
    protected $signature = 'token:create
                            {user_id? : ID user (tabel users). Skip untuk token system.}
                            {name? : Nama/label token, cth: "Mobile App"}
                            {--abilities=* : Izin, cth: usersprofile:read usersprofile:write (default: *)}
                            {--days=365 : Masa berlaku dalam hari}';

    protected $description = 'Generate API token baru (Bearer / X-API-Key). Plain text hanya muncul SEKALI.';

    public function handle(): int
    {
        $userId = $this->argument('user_id');
        $name   = $this->argument('name') ?? 'API Token';
        $abilities = array_values(array_filter($this->option('abilities'), fn($a) => $a !== ''));
        if (empty($abilities)) $abilities = ['*'];
        $days   = (int) $this->option('days');
        $expiresAt = $days > 0 ? now()->addDays($days)->format('Y-m-d H:i:s') : null;

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║              🔐 GENERATE API TOKEN — INDONESIA DEFENCE        ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        // ---- Cek user ----
        $user = null;
        if ($userId !== null) {
            $user = User::find((int) $userId);
            if (!$user) {
                $this->error("❌ User ID {$userId} tidak ditemukan di tabel users.");
                $this->info('');
                $this->info('Daftar user yang tersedia:');
                $this->table(['ID', 'Name', 'Email'], User::select('id','name','email')->limit(10)->get()->toArray());
                return Command::FAILURE;
            }
        }

        // ---- Buat token ----
        if ($user) {
            [$row, $plain] = PersonalAccessToken::createForUser($user, $name, $abilities, $expiresAt);
        } else {
            // Token system (tanpa user binding)
            $plain = 'ids_' . bin2hex(random_bytes(32));
            $row = PersonalAccessToken::create([
                'tokenable_id'   => 0,
                'tokenable_type' => 'system',
                'name'           => $name,
                'token'          => hash('sha256', $plain),
                'abilities'      => $abilities,
                'expires_at'     => $expiresAt,
            ]);
        }

        // ---- Tampilkan ----
        $this->line('  Nama Token   : <fg=white;options=bold>' . $row->name . '</>');
        $this->line('  ID Token     : <fg=white>' . $row->id . '</>');
        if ($user) {
            $this->line('  User         : <fg=white>' . $user->name . ' (' . $user->email . ')</>');
        } else {
            $this->line('  User         : <fg=white>SYSTEM (tanpa binding user)</>');
        }
        $this->line('  Abilities    : <fg=white>' . implode(', ', $abilities) . '</>');
        $this->line('  Berlaku Hingga: <fg=white>' . ($expiresAt ?? 'Selamanya') . '</>');
        $this->info('');
        $this->warn('  ┌────────────────────────────────────────────────────────────┐');
        $this->warn('  │  ⚠️  PLAIN TEXT TOKEN HANYA MUNCUL SEKALI. SIMPAN BAIK-BAIK  │');
        $this->warn('  └────────────────────────────────────────────────────────────┘');
        $this->info('');
        $this->line('  <bg=black;fg=green;options=bold>' . $plain . '</>');
        $this->info('');
        $this->line('  <fg=gray>Cara pakai (pilih salah satu):</>');
        $this->line('    Header  : <fg=cyan>Authorization: Bearer [REDACTED]');
        $this->line('    Header  : <fg=cyan>X-API-Key: ' . $plain . '</>');
        $this->info('');

        return Command::SUCCESS;
    }
}
