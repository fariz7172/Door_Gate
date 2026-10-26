<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UsersProfile;
use App\Models\DeviceGate;
use App\Models\SyncGateLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncUsersToGateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:sync-gate 
                            {--sn= : Serial number mesin tertentu (misal: ZXRL23103798)}
                            {--force : Paksa push meski mesin terdeteksi offline / bypass cek status}
                            {--chunk=50 : Ukuran chunk per batch pemrosesan (default: 50)}
                            {--loop : Jalankan berulang otomatis setiap 5 menit (daemon mode)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi bertahap (chunking) data UsersProfile ke gate port 787 hanya ke mesin yang aktif terhubung';

    /**
     * URL API daemon hardware lokal
     */
    protected string $daemonBase = 'http://localhost:787';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $loop = (bool) $this->option('loop');

        if ($loop) {
            $this->info("=== Memulai Sync Daemon UsersProfile -> Gate Server (Loop setiap 5 menit) ===");
            $this->comment("Fitur: Pre-Flight Device Check (Hanya kirim ke mesin AKTIF) + Chunking Memory Safe");
            $this->comment("Tekan Ctrl+C untuk menghentikan daemon.\n");

            while (true) {
                $this->runSync();

                $this->line("\n[".now()->format('Y-m-d H:i:s')."] Menunggu 5 menit untuk siklus pemeriksaan berikutnya...");
                for ($s = 0; $s < 300; $s++) {
                    sleep(1);
                }
            }
        } else {
            $this->runSync();
        }

        return Command::SUCCESS;
    }

    /**
     * Menjalankan satu siklus sinkronisasi dengan Pre-Check Mesin Aktif & Chunking
     */
    protected function runSync(): void
    {
        $nowStr = now()->format('Y-m-d H:i:s');
        $this->line("[{$nowStr}] Memeriksa status perangkat & antrean pembaruan UsersProfile...");

        // 1. TAHAP PRE-CHECK: Cek daftar mesin yang sedang online / terhubung ke socket server port 7788
        $activeDevices = $this->getActiveDevices();
        $isForce = (bool) $this->option('force');

        if (empty($activeDevices) && !$isForce) {
            $this->warn("⚠️  PEMBERITAHUAN STATUS PERANGKAT:");
            $this->warn("   Semua mesin gerbang saat ini OFFLINE (belum ada turnstile yang terkoneksi ke port 7788).");
            $this->line("   -> Sinkronisasi DITUNDA ke siklus 5 menit berikutnya.");
            $this->line("   -> Kolom `updated_at` TIDAK diubah, sehingga data personel tetap aman di antrean.");
            $this->comment("   (Gunakan opsi --force jika ingin tetap memaksakan kirim request).");
            return;
        }

        if (!empty($activeDevices)) {
            $this->info("✓ Mesin Aktif Terdeteksi (" . count($activeDevices) . " unit): [" . implode(', ', $activeDevices) . "]");
        }

        // 2. Query dasar user yang memerlukan sinkronisasi
        $query = UsersProfile::query();

        if (!$isForce) {
            $query->where(function ($q) {
                $q->whereNull('updated_at')
                  ->orWhereRaw("STR_TO_DATE(api_last_update, '%Y-%m-%d %H:%i:%s') > updated_at")
                  ->orWhereRaw("api_last_update > updated_at");
            });
        }

        $totalUsers = (clone $query)->count();

        if ($totalUsers === 0) {
            $this->info("✓ Semua data personel sudah up-to-date. Tidak ada antrean yang perlu disinkronkan.");
            return;
        }

        $chunkSize = (int) $this->option('chunk');
        if ($chunkSize < 5 || $chunkSize > 500) {
            $chunkSize = 50;
        }

        $totalChunks = (int) ceil($totalUsers / $chunkSize);
        $this->warn("Ditemukan {$totalUsers} personel dalam antrean. Memproses dalam {$totalChunks} chunk (batch: {$chunkSize} user/chunk)...");

        $allDevicesInDb = DeviceGate::all();
        $optionSn       = $this->option('sn');
        $pushedCount    = 0;
        $skippedCount   = 0;
        $currentChunk   = 0;

        // 3. Eksekusi per-chunk menggunakan chunkById (hemat RAM)
        $query->with('devices.gate')->chunkById($chunkSize, function ($users) use (
            &$pushedCount, &$skippedCount, &$currentChunk, $totalChunks, $allDevicesInDb, $optionSn, $activeDevices, $isForce
        ) {
            $currentChunk++;
            $this->line("----------------------------------------------------------------------");
            $this->info(">>> Memproses Chunk [{$currentChunk}/{$totalChunks}] — Jumlah: " . $users->count() . " personel");

            foreach ($users as $user) {
                $this->line("• User #{$user->ID} [{$user->EMPLOYEE_ID}] {$user->NAME}");

                // Tentukan target SN mesin yang seharusnya menerima data user ini
                $candidateSns = [];
                if (!empty($optionSn)) {
                    $candidateSns[] = trim($optionSn);
                } elseif ($user->devices->isNotEmpty()) {
                    foreach ($user->devices as $uxd) {
                        if ($uxd->gate && !empty($uxd->gate->sn)) {
                            $candidateSns[] = trim($uxd->gate->sn);
                        }
                    }
                }

                if (empty($candidateSns)) {
                    foreach ($allDevicesInDb as $d) {
                        if (!empty($d->sn)) {
                            $candidateSns[] = trim($d->sn);
                        }
                    }
                }

                $candidateSns = array_unique($candidateSns);

                // PILAH: Pisahkan antara mesin yang AKTIF vs mesin yang OFFLINE
                $targetOnlineSns = [];
                foreach ($candidateSns as $sn) {
                    if ($isForce || in_array($sn, $activeDevices, true)) {
                        $targetOnlineSns[] = $sn;
                    } else {
                        $this->comment("  [SKIP] Mesin SN [{$sn}] sedang offline, ditangguhkan.");
                    }
                }

                // Jika TIDAK ADA satupun target mesin yang online untuk user ini:
                if (empty($targetOnlineSns)) {
                    $this->warn("  [DELAY] Tidak ada mesin tujuan yang online untuk user ini. updated_at TIDAK diubah agar dicoba lagi nanti.");
                    $skippedCount++;
                    continue;
                }

                $fdata = $this->extractFdata($user);
                $anySuccess = false;

                foreach ($targetOnlineSns as $sn) {
                    // Cari type dari device jika tersedia, fallback ke 50 (Face)
                    $deviceType = '50';
                    $matchedDevice = $allDevicesInDb->firstWhere('sn', $sn);
                    if ($matchedDevice && isset($matchedDevice->type) && is_numeric($matchedDevice->type)) {
                        $deviceType = (string) $matchedDevice->type;
                    }

                    // A. PUSH REGISTERFACE: POST http://localhost:787/sztimmy/registerface
                    $facePayload = [
                        'sn'         => (string) $sn,
                        'userid'     => (string) $user->ID,
                        'username'   => (string) ($user->NAME ?? 'User ' . $user->ID),
                        'type'       => $deviceType,
                        'admin'      => '0',
                        'cardnumber' => (string) ($user->Card ?? '0'),
                        'timezone'   => '0',
                        'fdata'      => $fdata,
                    ];

                    $resFace = $this->sendHttp("{$this->daemonBase}/sztimmy/registerface", $facePayload);

                    // Catat ke tabel sync_gate_logs
                    try {
                        SyncGateLog::create([
                            'user_id'         => $user->ID,
                            'employee_id'     => $user->EMPLOYEE_ID,
                            'name'            => $user->NAME,
                            'card'            => $user->Card,
                            'sn'              => (string)$sn,
                            'endpoint'        => 'registerface',
                            'status'          => $resFace['ok'] ? 'SUCCESS' : 'FAILED',
                            'http_status'     => $resFace['status'] ?? null,
                            'message'         => $resFace['msg'] ?? ($resFace['ok'] ? 'OK' : 'Error response'),
                            'payload_summary' => [
                                'type'       => $deviceType,
                                'has_photo'  => !empty($fdata),
                                'cardnumber' => (string)($user->Card ?? '0'),
                            ],
                            'synced_at'       => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    if ($resFace['ok']) {
                        $this->info("  [OK] registerface -> Mesin SN [{$sn}]");
                        $anySuccess = true;
                    } else {
                        $this->error("  [FAIL] registerface -> Mesin SN [{$sn}]: " . ($resFace['msg'] ?? 'Gagal'));
                    }

                    // B. PUSH USERACCESS: POST http://localhost:787/sztimmy/useraccess
                    $startTime = $user->BEGIN_DATE ? $user->BEGIN_DATE->format('Y-m-d H:i:s') : '2020-01-01 00:00:00';
                    $endTime   = $user->END_DATE ? $user->END_DATE->format('Y-m-d H:i:s') : '2079-12-31 23:59:59';

                    $accessPayload = [
                        [
                            'sn'        => (string) $sn,
                            'userid'    => (string) $user->ID,
                            'starttime' => $startTime,
                            'endtime'   => $endTime,
                            'weekzone'  => 0,
                        ]
                    ];

                    $resAccess = $this->sendHttp("{$this->daemonBase}/sztimmy/useraccess", $accessPayload);

                    // Catat ke tabel sync_gate_logs
                    try {
                        SyncGateLog::create([
                            'user_id'         => $user->ID,
                            'employee_id'     => $user->EMPLOYEE_ID,
                            'name'            => $user->NAME,
                            'card'            => $user->Card,
                            'sn'              => (string)$sn,
                            'endpoint'        => 'useraccess',
                            'status'          => $resAccess['ok'] ? 'SUCCESS' : 'FAILED',
                            'http_status'     => $resAccess['status'] ?? null,
                            'message'         => $resAccess['msg'] ?? ($resAccess['ok'] ? 'OK' : 'Error response'),
                            'payload_summary' => [
                                'starttime' => $startTime,
                                'endtime'   => $endTime,
                                'weekzone'  => 0,
                            ],
                            'synced_at'       => now(),
                        ]);
                    } catch (\Throwable $e) {}

                    if ($resAccess['ok']) {
                        $this->info("  [OK] useraccess   -> Mesin SN [{$sn}] ({$startTime} s/d {$endTime})");
                        $anySuccess = true;
                    } else {
                        $this->error("  [FAIL] useraccess   -> Mesin SN [{$sn}]: " . ($resAccess['msg'] ?? 'Gagal'));
                    }
                }

                // C. HANYA PERBARUI updated_at JIKA MINIMAL 1 PUSH BERHASIL DITERIMA MESIN AKTIF
                if ($anySuccess) {
                    $pushTimestamp = now()->format('Y-m-d H:i:s');
                    DB::table('usersprofile')->where('ID', $user->ID)->update([
                        'updated_at' => $pushTimestamp,
                    ]);
                    $this->line("  => updated_at dicap: {$pushTimestamp}");
                    $pushedCount++;
                } else {
                    $this->warn("  => Gagal terkirim ke mesin. updated_at TIDAK diubah agar otomatis di-retry.");
                    $skippedCount++;
                }
            }

            unset($users);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }, 'ID');

        $this->line("----------------------------------------------------------------------");
        $this->info("=== Ringkasan Sinkronisasi Selesai ===");
        $this->info("Berhasil Terkirim ke Mesin Aktif : {$pushedCount} user");
        if ($skippedCount > 0) {
            $this->warn("Ditangguhkan (Mesin Offline)     : {$skippedCount} user (tersimpan aman di antrean)");
        }
    }

    /**
     * Memeriksa daftar mesin yang sedang online / terhubung ke socket port 7788
     */
    protected function getActiveDevices(): array
    {
        try {
            $res = Http::timeout(3)->get("{$this->daemonBase}/sztimmy/active-devices");
            if ($res->successful()) {
                $body = $res->json();
                return $body['registered_devices'] ?? [];
            }
        } catch (\Throwable $e) {
            // Jika port 787 belum siap
        }

        return [];
    }

    /**
     * Kirim HTTP Request ke daemon
     */
    protected function sendHttp(string $url, array $payload): array
    {
        try {
            $response = Http::timeout(8)->post($url, $payload);
            $body = $response->json();

            if ($response->successful()) {
                return ['ok' => true, 'data' => $body];
            }

            $errMsg = $body['message'] ?? $body['reason'] ?? $response->body();
            return ['ok' => false, 'status' => $response->status(), 'msg' => $errMsg];
        } catch (\Throwable $e) {
            return ['ok' => false, 'status' => 500, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Ekstraksi fdata (wajah) dari kolom photo
     */
    protected function extractFdata(UsersProfile $user): string
    {
        $raw = DB::table('usersprofile')->where('ID', $user->ID)->value('photo');

        if (empty($raw)) {
            return '';
        }

        if (is_string($raw) && str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            return $parts[1] ?? '';
        }

        if (is_string($raw)) {
            $decoded = base64_decode($raw, true);
            if ($decoded !== false && base64_encode($decoded) === trim($raw)) {
                return trim($raw);
            }
        }

        return base64_encode($raw);
    }
}
