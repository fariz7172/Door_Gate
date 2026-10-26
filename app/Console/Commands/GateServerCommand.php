<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use App\Models\DeviceGate;
use App\Models\ApiEndpoint;

class GateServerCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gate:serve {--port=7788 : Port untuk perangkat Gate/Turnstile hardware} {--api-port=787 : Port untuk HTTP API legacy & perintah Livewire}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Menjalankan Laravel Gate Socket Daemon Server dengan dukungan penuh WebSocket & HTTP API';

    /**
     * Sesi koneksi aktif perangkat (Key: Serial Number, Value: Socket Resource)
     */
    protected array $registeredDevices = [];

    /**
     * Semua socket klien yang sedang terhubung
     */
    protected array $clientSockets = [];

    /**
     * Melacak socket mana saja yang menggunakan protokol WebSocket (sudah handshake)
     */
    protected array $webSocketClients = [];

    /** Last activity timestamp per socket id */
    protected array $lastActivity = [];

    /** Last sweep time */
    protected int $lastSweepAt = 0;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $devicePort = (int) $this->option('port');
        $apiPort = (int) $this->option('api-port');

        $this->info("🚀 [Gate Daemon] Memulai server pada Port {$devicePort} (Hardware Turnstile) dan Port {$apiPort} (HTTP API)...");

        // Reset semua status device menjadi offline (0) saat server baru menyala (mencegah false status)
        try {
            DeviceGate::query()->update(['flagstatus' => 0]);
            $this->info("🔄 [Status Reset] Semua status mesin di-reset ke Disconnect (0).");
        } catch (\Exception $e) {
            $this->warn("⚠️ [Warning] Gagal mereset status mesin: " . $e->getMessage());
        }

        // 1. Buat Socket Listener untuk Hardware Turnstile (Default Port 7788)
        $deviceServer = stream_socket_server("tcp://0.0.0.0:{$devicePort}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$deviceServer) {
            $this->error("❌ Gagal membuat Hardware Socket Server pada port {$devicePort}: {$errstr} ({$errno})");
            return Command::FAILURE;
        }
        stream_set_blocking($deviceServer, false);

        // 2. Buat Socket Listener untuk HTTP API / Internal Command (Default Port 787)
        $apiServer = stream_socket_server("tcp://0.0.0.0:{$apiPort}", $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$apiServer) {
            $this->error("❌ Gagal membuat HTTP API Server pada port {$apiPort}: {$errstr} ({$errno})");
            return Command::FAILURE;
        }
        stream_set_blocking($apiServer, false);

        $this->info("✅ [Gate Daemon] Berjalan aktif. Menunggu koneksi dari mesin gerbang (Port {$devicePort}) & HTTP API (Port {$apiPort})...");

        $sockets = [$deviceServer, $apiServer];

        while (true) {
            $readSockets = array_merge($sockets, $this->clientSockets);
            $writeSockets = null;
            $exceptSockets = null;

            if (stream_select($readSockets, $writeSockets, $exceptSockets, 0, 200000) < 1) {
                continue;
            }

            foreach ($readSockets as $socket) {
                // A. Koneksi Baru dari Mesin Hardware (Port 7788)
                if ($socket === $deviceServer) {
                    $newClient = stream_socket_accept($deviceServer, 0);
                    if ($newClient) {
                        stream_set_blocking($newClient, false);
                        $this->clientSockets[(int) $newClient] = $newClient;
                        $this->lastActivity[(int) $newClient] = time();
                        // TCP keepalive agar putus terdeteksi lebih cepat (60s idle -> probe)
                        try {
                            $sock = @socket_import_stream($newClient);
                            if($sock){
                                @socket_set_option($sock, SOL_SOCKET, SO_KEEPALIVE, 1);
                                if(defined('TCP_KEEPIDLE')) @socket_set_option($sock, SOL_TCP, TCP_KEEPIDLE, 30);
                                if(defined('TCP_KEEPINTVL')) @socket_set_option($sock, SOL_TCP, TCP_KEEPINTVL, 10);
                                if(defined('TCP_KEEPCNT')) @socket_set_option($sock, SOL_TCP, TCP_KEEPCNT, 3);
                            }
                        } catch (\Throwable $e) {}
                        $peer = @stream_socket_get_name($newClient, true) ?: 'Unknown IP';
                        $this->info("⚡ [Hardware Connected] Mesin gerbang terhubung dari IP {$peer} (Socket ID: " . (int) $newClient . ")");
                    }
                    continue;
                }

                // B. Koneksi Baru dari HTTP API / Livewire (Port 787)
                if ($socket === $apiServer) {
                    $newApiClient = stream_socket_accept($apiServer, 0);
                    if ($newApiClient) {
                        stream_set_blocking($newApiClient, false);
                        $this->clientSockets[(int) $newApiClient] = $newApiClient;
                        $this->lastActivity[(int) $newApiClient] = time();
                    }
                    continue;
                }

                // C. Data Masuk dari Klien
                $data = @fread($socket, 8192);
                
                // Pada non-blocking socket, jika $data kosong TAPI feof false, berarti socket masih hidup!
                if ($data === false) {
                    $this->disconnectClient($socket, 'read_false');
                    continue;
                }
                if ($data === '' && @feof($socket)) {
                    $this->disconnectClient($socket, 'feof_peer_closed');
                    continue;
                }
                if ($data === '') {
                    $this->lastActivity[(int)$socket] = time();
                    continue; // Socket masih aktif, hanya belum ada data tambahan
                }

                // BACA SAMPAI HABIS jika masih ada sisa data di dalam buffer OS
                while (($more = @fread($socket, 8192)) !== false && $more !== '') {
                    $data .= $more;
                }

                // Balas 100 Continue jika diminta (biasanya oleh cURL untuk payload besar)
                if (preg_match('/Expect:\s*100-continue/i', $data)) {
                    @fwrite($socket, "HTTP/1.1 100 Continue\r\n\r\n");
                }

                // JIKA INI REQUEST HTTP DENGAN CONTENT-LENGTH (Misal upload foto Base64 berukuran besar > 8KB)
                // Pastikan seluruh body sudah terbaca dengan sempurna sebelum diproses!
                if (preg_match('/Content-Length:\s*(\d+)/i', $data, $m)) {
                    $expectedLen = (int) $m[1];
                    $bodyPos = strpos($data, "\r\n\r\n");
                    if ($bodyPos !== false) {
                        $bodyPos += 4;
                    } elseif (($bodyPos = strpos($data, "\n\n")) !== false) {
                        $bodyPos += 2;
                    }
                    
                    if ($bodyPos !== false) {
                        $currentLen = strlen($data) - $bodyPos;
                        $attempts = 0;
                        while ($currentLen < $expectedLen && $attempts < 200) {
                            $more = @fread($socket, 65536); // Baca dengan buffer lebih besar (64KB)
                            if ($more !== false && $more !== '') {
                                $data .= $more;
                                $currentLen = strlen($data) - $bodyPos;
                                $attempts = 0; // Reset timeout karena data masih mengalir
                            } else {
                                usleep(10000); // Tunggu 10ms jika buffer OS masih kosong
                                $attempts++;
                            }
                        }
                    }
                }

                $this->lastActivity[(int)$socket] = time();
                $this->processIncomingData($socket, $data);
            }

            // Heartbeat / Keepalive sweep setiap 15 detik agar koneksi mesin tidak idle dan tidak time out
            if (time() - $this->lastSweepAt >= 15) {
                $this->lastSweepAt = time();
                $now = time();

                // Kirim WebSocket Ping (Opcode 9) ke semua mesin aktif
                foreach ($this->registeredDevices as $sn => $dsock) {
                    if (is_resource($dsock)) {
                        @fwrite($dsock, pack('CC', 0x89, 0));
                    }
                }

                // Putuskan socket non-device yang idle > 90 detik
                foreach ($this->clientSockets as $cid => $csock) {
                    $last = $this->lastActivity[$cid] ?? $now;
                    if ($now - $last > 90) {
                        $this->warn("⏱️ [Heartbeat Timeout] Socket ID {$cid} idle ".($now-$last)."s — putuskan.");
                        $this->disconnectClient($csock, 'heartbeat_timeout_'.($now-$last).'s');
                    }
                }
            }
        }
    }

    /**
     * Memproses data yang masuk ke socket
     */
    protected function processIncomingData($socket, string $rawData): void
    {
        $id = (int) $socket;

        // 1. Cek apakah ini permintaan WebSocket Handshake (contoh: GET /pub/chat HTTP/1.1 ... Upgrade: websocket)
        if (stripos($rawData, 'Upgrade: websocket') !== false) {
            $this->handleWebSocketHandshake($socket, $rawData);
            return;
        }

        // 2. Cek apakah ini permintaan HTTP API standar (dari Postman/browser pada Port 787)
        if (!isset($this->webSocketClients[$id]) && (str_starts_with($rawData, 'POST ') || str_starts_with($rawData, 'GET ') || str_contains($rawData, 'HTTP/1.'))) {
            $this->handleHttpCommand($socket, $rawData);
            return;
        }

        // 3. Jika socket ini adalah WebSocket, kita harus men-decode frame-nya (unmasking)
        $data = $rawData;
        if (isset($this->webSocketClients[$id])) {
            $data = $this->decodeWebSocketFrame($socket, $rawData);
            if ($data === null || $data === '') {
                return;
            }
        }

        $data = trim($data);
        $json = json_decode($data, true);
        if (!$json) {
            return;
        }

        $sn = $json['sn'] ?? ($json['SN'] ?? null);

        // A. Registrasi Serial Number Mesin ke memori (_registeredDevices)
        if ($sn) {
            $this->registeredDevices[$sn] = $socket;
            $this->info("🟢 [Device Registered] Mesin SN [{$sn}] teregistrasi di memori dan siap menerima instruksi!");
            
            // Auto Update status ke Connect (1)
            try {
                $updated = DeviceGate::where('sn', $sn)->update(['flagstatus' => 1]);
                if ($updated === 0) {
                    // Coba fallback update berdasarkan curip jika ada
                    $curIp = $json['devinfo']['curip'] ?? null;
                    if ($curIp) {
                        // Normalisasi IP jika ada leading zeros seperti 192.168.001.224 -> 192.168.1.224
                        $normIp = preg_replace('/\b0+(\d)/', '$1', $curIp);
                        DeviceGate::where('ip', $normIp)->orWhere('ip', $curIp)->update(['sn' => $sn, 'flagstatus' => 1]);
                        $this->info("🔄 [Auto-Sync SN] SN mesin diperbarui ke [{$sn}] berdasarkan IP [{$normIp}]");
                    } else {
                        $this->warn("⚠️ [Warning] Mesin SN [{$sn}] terhubung tetapi belum cocok dengan data di tabel devicegate!");
                    }
                }
            } catch (\Exception $e) {
                $this->warn("⚠️ [Warning] Gagal update status Connect untuk SN [{$sn}]: " . $e->getMessage());
            }
        }

        // B. Balasan untuk perintah registrasi awal mesin ("cmd": "reg")
        if (isset($json['cmd']) && $json['cmd'] === 'reg') {
            $this->info("📥 [Reg Received] Raw: " . json_encode($json));
            $replyObj = [
                'ret' => 'reg',
                'result' => true,
                'cloudtime' => now()->format('Y-m-d H:i:s'),
            ];
            $this->sendToDeviceSocket($socket, json_encode($replyObj));
            $this->info("📤 [Reg Acknowledge] Mengirim konfirmasi registrasi ke SN [{$sn}]");
        }

        // B2. QR Code scan dari mesin — langsung hit FitQuarters API (port dari contoh_kode_vb.txt "sendqrcode")
        if (isset($json['cmd']) && $json['cmd'] === 'sendqrcode') {
            $this->handleSendQrCode($socket, $json);
            return;
        }

        // C. Tangani log absensi / check-in event (Mendukung 'sendlog' dari mesin turnstile)
        if (isset($json['cmd']) && in_array($json['cmd'], ['sendlog', 'newlog', 'realtime_log', 'checkin'])) {
            $this->saveAttendanceLog($json);
            
            // Balas konfirmasi (acknowledgement) ke mesin agar mesin tahu log sudah diterima
            $replyObj = [
                'ret' => $json['cmd'],
                'result' => true,
                'cloudtime' => now()->format('Y-m-d H:i:s'),
                'access' => 1,
                'message' => 'Silahkan Masuk'
            ];
            $this->sendToDeviceSocket($socket, json_encode($replyObj));
            $this->info("📤 [Log Acknowledge] Mengirim konfirmasi penerimaan log absensi ke SN [{$sn}]");
        }

        // D. Log respons status dari mesin
        if (isset($json['result'])) {
            $status = $json['result'] ? 'SUKSES' : 'GAGAL';
            $safeJson = $this->safeDeviceResponseForLog($json);
            $line = "📨 [Device Response] Status: {$status} | SN: {$sn} | Data: " . json_encode($safeJson, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $this->line($line);
            $this->appendGateLog($line);

            // Simpan respons KEGAGALAN daftar wajah (setuserinfo) ke tbl_userreg,
            // dengan USER_ADDR mengisi enrollid personel yang dikirim, bukan id di teks.
            if ((bool) $json['result'] === false && ($json['ret'] ?? '') === 'setuserinfo') {
                $this->saveUserRegFailure($json);
            }
        }
    }

    /**
     * Menyimpan kegagalan pendaftaran wajah ke tbl_userreg.
     * USER_ADDR memakai enrollid yang dikirim; NULL jika tidak tersedia.
     */
    protected function saveUserRegFailure(array $json): void
    {
        try {
            $msg = trim((string) ($json['msg'] ?? ($json['message'] ?? '')));
            $reasonCode = $json['reason'] ?? '';

            // Peta kode firmware -> teks. JANGAN dianggap definitive: tidak ada dokumen
            // firmware resmi di proyek ini; 12=face is double hanya inferensi dari
            // respons saat foto personel terbukti identik (hash SHA256 sama).
            $reasonText = $this->translateReasonCode($reasonCode);

            $description = $msg;
            if ($reasonText !== '') {
                $description .= ($description !== '' ? ' ' : '') . $reasonText;
            }

            $identity = isset($json['enrollid']) && is_numeric($json['enrollid'])
                ? (int) $json['enrollid']
                : null;

            DB::table('tbl_userreg')->insert([
                'USER_ADDR'   => $identity,
                'description' => mb_substr($description, 0, 1000),
                'created_at'  => now()->format('Y-m-d H:i:s'),
            ]);

            $this->warn('⚠️ [UserReg] Kegagalan daftar wajah disimpan ke tbl_userreg: enrollid='
                . var_export($identity, true) . " desc='{$description}'");
        } catch (\Throwable $e) {
            $this->warn('⚠️ Gagal menyimpan ke tbl_userreg: ' . $e->getMessage());
        }
    }

    /**
     * Peta kode alasan firmware -> teks yang dibaca manusia.
     * Label BELUM DIVERIFIKASI dipasang karena tidak ada dokumentasi firmware;
     * kode yang tidak dikenal tetap ditampilkan apa adanya.
     */
    protected function translateReasonCode($reason): string
    {
        if ($reason === '' || $reason === null) {
            return '';
        }
        $key = (string) $reason;
        $map = [
            '12' => 'face is double [BELUM DIVERIFIKASI]',
        ];
        if (isset($map[$key])) {
            return $map[$key];
        }
        return '(reason=' . $key . ' [KODE TIDAK DIKENAL])';
    }

    /**
     * Menghapus payload biometrik/token dari log agar tidak membocorkan data sensitif.
     */
    protected function safeDeviceResponseForLog(array $json): array
    {
        foreach ($json as $key => $value) {
            if (in_array(strtolower((string) $key), ['record', 'fdata', 'fpdata', 'photo', 'token', 'password', 'private_key'], true)) {
                $json[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $json[$key] = $this->safeDeviceResponseForLog($value);
            }
        }

        return $json;
    }

    /**
     * Menulis respons perangkat ke log khusus gate.
     */
    protected function appendGateLog(string $line): void
    {
        try {
            $path = storage_path('logs/gate.log');
            $dir = dirname($path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $written = @file_put_contents($path, '[' . now()->format('Y-m-d H:i:s') . '] ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($written === false) {
                $this->warn('Gagal menulis gate.log. Periksa izin dan ruang penyimpanan.');
            }
        } catch (\Throwable $e) {
            $this->warn('⚠️ Gagal menulis gate.log: ' . $e->getMessage());
        }
    }

    /**
     * Melakukan WebSocket Handshake (RFC 6455) agar mesin turnstile tetap terhubung di /pub/chat
     */
    protected function handleWebSocketHandshake($socket, string $headers): void
    {
        if (preg_match('/Sec-WebSocket-Key:\s*(.+)\r?\n/i', $headers, $matches)) {
            $key = trim($matches[1]);
            $acceptKey = base64_encode(sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11", true));
            
            $upgradeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: {$acceptKey}\r\n\r\n";

            @fwrite($socket, $upgradeResponse);
            $this->webSocketClients[(int)$socket] = true;
            $this->info("🤝 [WebSocket Handshake] Berhasil handshake WebSocket dengan mesin (Socket ID: " . (int)$socket . ")");
        } else {
            $this->warn("⚠️ [Handshake Failed] Header Sec-WebSocket-Key tidak ditemukan.");
            $this->disconnectClient($socket);
        }
    }

    /**
     * Menangani request HTTP dari Port 787
     */
    protected function handleHttpCommand($socket, string $httpData): void
    {
        $lines = explode("\r\n", $httpData);
        $requestLine = $lines[0] ?? '';
        
        $path = '';
        if (preg_match('/^(?:POST|GET)\s+([^\s?]+)/i', $requestLine, $matches)) {
            $path = strtolower(trim($matches[1]));
        }

        // Endpoint Cek Status & Daftar Mesin yang Sedang Aktif/Online di Socket 7788
        if (str_contains($path, 'status') || str_contains($path, 'devices') || str_contains($path, 'active-devices')) {
            $this->sendHttpResponse($socket, 200, [
                'status' => 'success',
                'active_devices_count' => count($this->registeredDevices),
                'registered_devices' => array_keys($this->registeredDevices),
                'timestamp' => now()->format('Y-m-d H:i:s'),
            ]);
            return;
        }

        // Fleksibel mengambil HTTP body
        $body = '';
        if (($pos = strpos($httpData, "\r\n\r\n")) !== false) {
            $body = substr($httpData, $pos + 4);
        } elseif (($pos = strpos($httpData, "\n\n")) !== false) {
            $body = substr($httpData, $pos + 2);
        }
        if (empty(trim($body))) {
            $posObj = strpos($httpData, '{');
            $posArr = strpos($httpData, '[');
            if ($posObj !== false && ($posArr === false || $posObj < $posArr)) {
                $body = substr($httpData, $posObj);
            } elseif ($posArr !== false) {
                $body = substr($httpData, $posArr);
            }
        }

        // PARSING BODY JSON
        $params = [];
        $jsonBody = json_decode(trim($body), true);
        if (is_array($jsonBody)) {
            $params = $jsonBody;
        } else {
            parse_str(trim($body), $params);
        }

        $items = [];
        if (isset($params[0]) && is_array($params[0])) {
            $items = $params;
        } elseif (!empty($params)) {
            $items = [$params];
        }

        $sn = $items[0]['sn'] ?? ($items[0]['SN'] ?? ($params['sn'] ?? ($params['SN'] ?? null)));
        $commandsToSend = [];

        if (!empty($params['cmd'])) {
            if (is_string($params['cmd']) && str_starts_with($params['cmd'], '{')) {
                $commandsToSend[] = $params['cmd'];
            } else {
                $commandsToSend[] = json_encode($params);
            }
        } 
        elseif ($sn) {
            if (str_contains($path, 'opengate') || str_contains($path, 'opendoor')) {
                $commandsToSend[] = json_encode(['cmd' => 'opendoor', 'doornum' => 1]);
            } elseif (str_contains($path, 'reboot')) {
                $commandsToSend[] = json_encode(['cmd' => 'reboot']);
            } elseif (str_contains($path, 'cleanlogs') || str_contains($path, 'cleanlog')) {
                $commandsToSend[] = json_encode(['cmd' => 'cleanlog']);
            } elseif (str_contains($path, 'getuserlist')) {
                $commandsToSend[] = json_encode(['cmd' => 'getuserlist', 'stn' => true]);
            } elseif (str_contains($path, 'settime')) {
                $rawTime = $params['Time'] ?? ($params['time'] ?? ($params['cloudtime'] ?? ($params['datetime'] ?? now()->format('Y-m-d H:i:s'))));
                $cleanTime = date('Y-m-d H:i:s', strtotime($rawTime));
                $commandsToSend[] = json_encode([
                    'cmd' => 'settime',
                    'time' => $cleanTime,
                    'cloudtime' => $cleanTime,
                    'datetime' => $cleanTime,
                    'devtime' => $cleanTime,
                    'record' => $cleanTime,
                ]);
            } elseif (str_contains($path, 'useraccess') || str_contains($path, 'userlock')) {
                foreach ($items as $item) {
                    $userId = (int) ($item['userid'] ?? $item['enrollid'] ?? 0);
                    if ($userId > 0) {
                        $commandsToSend[] = json_encode([
                            'cmd' => 'setuserlock',
                            'count' => 1,
                            'record' => [
                                [
                                    'enrollid' => $userId,
                                    'weekzone' => (int) ($item['weekzone'] ?? $item['timezone'] ?? 0),
                                    'starttime' => $item['starttime'] ?? '2020-01-01 00:00:00',
                                    'endtime' => $item['endtime'] ?? '2030-12-31 23:59:59',
                                ]
                            ]
                        ]);
                    }
                }
            } elseif (str_contains($path, 'registerface') || str_contains($path, 'registface') || str_contains($path, 'registercard') || str_contains($path, 'registerfull')) {
                $userId = (int) ($params['userid'] ?? $params['enrollid'] ?? 0);
                $userName = $params['username'] ?? $params['name'] ?? '';
                $admin = (int) ($params['admin'] ?? 0);

                if (!empty($params['fdata']) || !empty($params['fpdata'])) {
                    $backupNum = isset($params['type']) && $params['type'] !== '' ? (int) $params['type'] : 50;
                    $commandsToSend[] = json_encode([
                        'cmd' => 'setuserinfo',
                        'enrollid' => $userId,
                        'name' => $userName,
                        'backupnum' => $backupNum,
                        'admin' => $admin,
                        'record' => $params['fdata'] ?? $params['fpdata'],
                    ]);
                }
                if (!empty($params['cardnumber']) && is_numeric($params['cardnumber']) && (float)$params['cardnumber'] > 0) {
                    $commandsToSend[] = json_encode([
                        'cmd' => 'setuserinfo',
                        'enrollid' => $userId,
                        'name' => $userName,
                        'backupnum' => 11,
                        'admin' => $admin,
                        'record' => (float) $params['cardnumber'],
                    ]);
                }
                if (empty($commandsToSend) && !empty($userName)) {
                    $commandsToSend[] = json_encode([
                        'cmd' => 'setusername',
                        'count' => 1,
                        'record' => [
                            ['enrollid' => $userId, 'name' => $userName]
                        ],
                    ]);
                }
            } elseif (str_contains($path, 'deleteface') || str_contains($path, 'deleteuser')) {
                $commandsToSend[] = json_encode([
                    'cmd' => 'deleteuser',
                    'enrollid' => (int) ($params['userid'] ?? $params['enrollid'] ?? 0),
                    'backupnum' => (int) ($params['backupnum'] ?? 13),
                ]);
            } elseif (str_contains($path, 'enableuser')) {
                $enFlag = (int) ($params['enflag'] ?? ($params['enFlag'] ?? ($params['eflag'] ?? ($params['eFlag'] ?? ($params['flag'] ?? 1)))));
                $commandsToSend[] = json_encode([
                    'cmd' => 'enableuser',
                    'enrollid' => (int) ($params['userid'] ?? $params['enrollid'] ?? 0),
                    'enflag' => $enFlag,
                ]);
            } elseif (str_contains($path, 'enabledevice')) {
                // 25 Sept 26 — samakan dengan VB.NET TASOFT: setdevinfo disable_match=0
                $commandsToSend[] = json_encode(['cmd' => 'setdevinfo', 'disable_match' => 0]);
            } elseif (str_contains($path, 'disabledevice')) {
                // 25 Sept 26 — samakan dengan VB.NET TASOFT: setdevinfo disable_match=1
                $commandsToSend[] = json_encode(['cmd' => 'setdevinfo', 'disable_match' => 1]);
            } elseif (str_contains($path, 'dayzone')) {
                foreach ($items as $item) {
                    $dayzones = [];
                    for ($i = 1; $i <= 8; $i++) {
                        $dayStr = $item["Day{$i}"] ?? ($item["day{$i}"] ?? '');
                        $sections = [];
                        if ($dayStr !== '') {
                            $parts = explode(';', $dayStr);
                            foreach ($parts as $part) {
                                if (trim($part) !== '') {
                                    $sections[] = ['section' => trim($part)];
                                }
                            }
                        }
                        if (empty($sections)) {
                            $sections[] = ['section' => '00:00~23:59'];
                        }
                        $dayzones[] = ['day' => $sections];
                    }
                    $commandsToSend[] = json_encode([
                        'cmd' => 'setdevlock',
                        'dayzone' => $dayzones
                    ]);
                }
            } elseif (str_contains($path, '/weekzone') && !str_contains($path, '/userweekzone')) {
                foreach ($items as $item) {
                    $weeks = [];

                    if (isset($item['week1']) || isset($item['Week1']) || isset($item['week2']) || isset($item['Week2'])) {
                        for ($w = 1; $w <= 8; $w++) {
                            $wStr = $item["week{$w}"] ?? ($item["Week{$w}"] ?? '');
                            $days = [];
                            if ($wStr !== '') {
                                $parts = explode(';', $wStr);
                                foreach ($parts as $p) {
                                    if (trim($p) !== '') {
                                        $days[] = ['day' => (int) trim($p)];
                                    }
                                }
                            }
                            while (count($days) < 7) {
                                $days[] = ['day' => 1];
                            }
                            $weeks[] = ['week' => $days];
                        }
                    } 
                    else {
                        $weekName = (int) ($item['WeekName'] ?? ($item['weekname'] ?? ($item['weekzone'] ?? 1)));
                        $weekStr = $item['Week'] ?? ($item['week'] ?? '');
                        $days = [];
                        if ($weekStr !== '') {
                            $parts = explode(';', $weekStr);
                            foreach ($parts as $p) {
                                if (trim($p) !== '') {
                                    $days[] = ['day' => (int) trim($p)];
                                }
                            }
                        }
                        while (count($days) < 7) {
                            $days[] = ['day' => 1];
                        }

                        $defaultWeek = [];
                        for ($d = 0; $d < 7; $d++) {
                            $defaultWeek[] = ['day' => 1];
                        }

                        for ($w = 1; $w <= 8; $w++) {
                            if ($w === $weekName) {
                                $weeks[] = ['week' => $days];
                            } else {
                                $weeks[] = ['week' => $defaultWeek];
                            }
                        }
                    }

                    $commandsToSend[] = json_encode([
                        'cmd' => 'setdevlock',
                        'weekzone' => $weeks
                    ]);
                }
            } elseif (str_contains($path, 'userweekzone')) {
                foreach ($items as $item) {
                    $userId = (int) ($item['userid'] ?? ($item['enrollid'] ?? 0));
                    $weekZone = (int) ($item['weekzone'] ?? 1);
                    if ($userId > 0) {
                        $commandsToSend[] = json_encode([
                            'cmd' => 'setuserlock',
                            'count' => 1,
                            'record' => [
                                [
                                    'enrollid' => $userId,
                                    'weekzone' => $weekZone
                                ]
                            ]
                        ]);
                    }
                }
            } elseif (str_contains($path, 'gettime')) {
                // Firmware TAS-F05S (ai810_f06v) tidak mengenali cmd 'gettime' via WebSocket dan akan mengirim opcode 8 close.
                // Jika koneksi sudah ada di $this->registeredDevices, kembalikan 200 langsung tanpa mengganggu mesin.
                $this->sendHttpResponse($socket, 200, [
                    'status' => 'success',
                    'sn' => $sn,
                    'is_connected' => true,
                    'server_time' => now()->format('Y-m-d H:i:s'),
                    'message' => "Mesin SN [{$sn}] terhubung aktif di server gateway.",
                ]);
                return;
            } elseif (str_contains($path, 'getuserdevice') || str_contains($path, 'getusersface') || str_contains($path, 'getuserscard')) {
                $commandsToSend[] = json_encode(['cmd' => 'getuserlist', 'stn' => true]);
            } elseif (str_contains($path, 'getdevlock')) {
                $commandsToSend[] = json_encode(['cmd' => 'getdevlock']);
            } elseif (str_contains($path, 'getuseraccess')) {
                $userId = (int) ($params['userid'] ?? ($params['enrollid'] ?? 0));
                $commandsToSend[] = json_encode(['cmd' => 'getuserlock', 'enrollid' => $userId]);
            } elseif (str_contains($path, 'newlog')) {
                $commandsToSend[] = json_encode(['cmd' => 'getnewlog']);
            } elseif (str_contains($path, 'sendlog') || str_contains($path, 'checkin') || str_contains($path, 'realtime_log')) {
                $this->saveAttendanceLog($params);
                $this->sendHttpResponse($socket, 200, [
                    'ret' => 'sendlog',
                    'result' => true,
                    'cloudtime' => now()->format('Y-m-d H:i:s'),
                    'access' => 1,
                    'message' => 'this is the reson of access'
                ]);
                return;
            }
        }

        if (!$sn || empty($commandsToSend)) {
            $this->warn("⚠️ [Bad Request] Rute: {$path} | SN atau Endpoint tidak dikenali. Payload length: " . strlen($body));
            $this->sendHttpResponse($socket, 400, [
                'status' => 'error',
                'message' => 'SN mesin tidak ditemukan, Check Koneksi Mesin',
                'path' => $path,
                'received_params_count' => count($params),
            ]);
            return;
        }

        if (!isset($this->registeredDevices[$sn])) {
            $this->warn("⚠️ [Command Failed] Mesin SN [{$sn}] belum terhubung ke server.");
            $this->sendHttpResponse($socket, 404, [
                'status' => 'error',
                'message' => "Mesin SN [{$sn}] offline atau belum terhubung ke socket server.",
                'registered_devices' => array_keys($this->registeredDevices),
            ]);
            return;
        }

        $targetSocket = $this->registeredDevices[$sn];
        
        foreach ($commandsToSend as $cmd) {
            $this->sendToDeviceSocket($targetSocket, $cmd);
            $this->info("⚡ [Command Sent] Meneruskan perintah dari API {$path} ke mesin SN [{$sn}]: " . substr($cmd, 0, 150) . (strlen($cmd) > 150 ? '...' : ''));
            if (count($commandsToSend) > 1) {
                usleep(50000);
            }
        }

        $this->sendHttpResponse($socket, 200, [
            'status' => 'success',
            'sn' => $sn,
            'commands_sent_count' => count($commandsToSend),
            'message' => 'Perintah berhasil diteruskan ke hardware turnstile.',
        ]);
    }

    /**
     * Mengirim string JSON ke socket mesin
     */
    protected function sendToDeviceSocket($targetSocket, string $payload): void
    {
        $id = (int) $targetSocket;
        if (isset($this->webSocketClients[$id])) {
            $framed = $this->encodeWebSocketFrame($payload);
            @fwrite($targetSocket, $framed);
        } else {
            @fwrite($targetSocket, $payload . "\r\n");
        }
    }

    /**
     * Membuka topeng (unmask/decode) frame dari klien WebSocket
     */
    protected function decodeWebSocketFrame($socket, string $data): ?string
    {
        $len = strlen($data);
        if ($len < 2) return null;

        $firstByte = ord($data[0]);
        $secondByte = ord($data[1]);

        $opcode = $firstByte & 0x0f;
        if ($opcode === 8) {
            $closeCode = 0;
            $closeReason = '';
            $payloadLen = $secondByte & 0x7f;
            $isMasked = ($secondByte & 0x80) === 0x80;
            $maskOffset = 2;
            if ($payloadLen === 126) $maskOffset = 4;
            elseif ($payloadLen === 127) $maskOffset = 10;
            
            if ($isMasked && strlen($data) >= $maskOffset + 4 + $payloadLen) {
                $maskKey = substr($data, $maskOffset, 4);
                $rawPayload = substr($data, $maskOffset + 4, $payloadLen);
                $unmasked = '';
                for ($i = 0; $i < strlen($rawPayload); $i++) {
                    $unmasked .= $rawPayload[$i] ^ $maskKey[$i % 4];
                }
                if (strlen($unmasked) >= 2) {
                    $closeCode = unpack('n', substr($unmasked, 0, 2))[1] ?? 0;
                    $closeReason = substr($unmasked, 2);
                }
            }
            
            // Echo Close frame response back to client (RFC 6455)
            @fwrite($socket, pack('CC', 0x88, 0));
            $this->disconnectClient($socket, "ws_close_code_{$closeCode}_reason_{$closeReason}");
            return null;
        }
        if ($opcode === 9) {
            @fwrite($socket, "\x8a\x00");
            return null;
        }

        $isMasked = ($secondByte & 0x80) === 0x80;
        $payloadLength = $secondByte & 0x7f;

        $offset = 2;
        if ($payloadLength === 126) {
            if ($len < 4) return null;
            $payloadLength = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLength === 127) {
            if ($len < 10) return null;
            $payloadLength = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        if (!$isMasked) {
            return substr($data, $offset, $payloadLength);
        }

        if ($len < $offset + 4 + $payloadLength) {
            return null;
        }

        $maskingKey = substr($data, $offset, 4);
        $payload = substr($data, $offset + 4, $payloadLength);

        $unmasked = '';
        for ($i = 0; $i < $payloadLength; $i++) {
            $unmasked .= $payload[$i] ^ $maskingKey[$i % 4];
        }

        return $unmasked;
    }

    /**
     * Membungkus string teks ke dalam frame WebSocket server
     */
    protected function encodeWebSocketFrame(string $text): string
    {
        $b1 = 0x81;
        $length = strlen($text);

        if ($length <= 125) {
            $header = pack('CC', $b1, $length);
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length);
        } else {
            $header = pack('CCJ', $b1, 127, $length);
        }

        return $header . $text;
    }

    /**
     * Mengirim respons HTTP JSON sederhana ke klien API
     */
    protected function sendHttpResponse($socket, int $statusCode, array $payload): void
    {
        $statusText = $statusCode === 200 ? 'OK' : ($statusCode === 404 ? 'Not Found' : 'Bad Request');
        $json = json_encode($payload, JSON_PRETTY_PRINT);
        $length = strlen($json);

        $response = "HTTP/1.1 {$statusCode} {$statusText}\r\n" .
            "Content-Type: application/json\r\n" .
            "Content-Length: {$length}\r\n" .
            "Access-Control-Allow-Origin: *\r\n" .
            "Connection: close\r\n\r\n" .
            $json;

        @fwrite($socket, $response);
        $this->disconnectClient($socket);
    }

    /**
     * Handler QR-code tap dari mesin: {"cmd":"sendqrcode","sn":"AYUG09087175","record":"M12345"}
     * Port langsung dari contoh_kode_vb.txt — cocok sama VB.NET clsCMD logic.
     * stat 0 = IN → POST /v1/check_ins, stat 1 = OUT → POST /v1/check_outs
     * Selalu log tbl_gate_apilog + balas access 1/0 ke mesin.
     */
    protected function handleSendQrCode($socket, array $json): void
    {
        try {
            $sn = $json['sn'] ?? ($json['SN'] ?? null);
            $record = $json['record'] ?? ($json['Record'] ?? ($json['qrcode'] ?? ($json['qr'] ?? '')));
            $record = trim((string) $record);

            if (!$sn || $record === '') {
                $this->warn("⚠️ [sendqrcode] sn/record kosong — sn={$sn} record={$record}");
                $this->sendToDeviceSocket($socket, json_encode(['ret' => 'sendqrcode', 'result' => false, 'access' => 0, 'message' => 'QRCODE TIDAK DIKENALI']));
                return;
            }

            // Ambil stat + Name dari devicegate (mirip VB: SELECT stat, Name FROM devicegate WHERE sn=@sn)
            $device = null;
            $stat = -1;
            $deviceName = '';
            try {
                $device = DeviceGate::where('sn', $sn)->first();
                if ($device) {
                    $stat = (int) ($device->stat ?? -1);
                    $deviceName = (string) ($device->name ?? $device->description ?? $sn);
                }
            } catch (\Exception $e) {
                Log::warning("[Gate sendqrcode] gagal query devicegate sn={$sn}: " . $e->getMessage());
            }

            $apiUrl = '';
            $reqBodyStr = '';
            $respStatusCode = 0;
            $respBodyStr = '';
            $isSuccess = false;
            $isFoundLocally = false;
            $memberName = null;

            if ($device && $stat !== -1) {
                // Resolve credentials dulu
                $baseUrl = rtrim(config('services.fitquarters.base_url', 'https://api.fitquarters.id'), '/');
                $apiKey  = (string) config('services.fitquarters.key', '');
                try {
                    if (Schema::hasTable('gym_settings')) {
                        $s = \App\Models\GymSetting::first();
                        if ($s) {
                            if (!empty($s->api_base_url)) $baseUrl = rtrim($s->api_base_url, '/');
                            if (!empty($s->api_key)) $apiKey = (string) $s->api_key;
                        }
                    }
                } catch (\Throwable $e) {}

                // 1. Validasi member access — HANYA saat stat=0 (IN). stat=1 (OUT) langsung lanjut.
                $denialReason = null;
                $checkMemberUrl = $baseUrl . '/v1/members/' . urlencode($record) . '/access';
                if ($stat === 0) {
                    try {
                        // Ambil URL check access dari ApiEndpoint (ID = 20)
                        try {
                            $ep20 = ApiEndpoint::find(20);
                            if ($ep20 && !empty($ep20->url)) {
                                $checkMemberUrl = str_replace(['{id}', '{member_id}', '{record}'], urlencode($record), $ep20->url);
                            }
                        } catch (\Throwable $e) {}

                        $respCheck = Http::withHeaders([
                            'Authorization' => 'Bearer ' . $apiKey,
                            'Accept' => 'application/json',
                        ])->timeout(4)->get($checkMemberUrl);

                        $respStatusCode = $respCheck->status();
                        $respBodyStr = $respCheck->body();
                        $apiUrl = $checkMemberUrl;
                        $reqBodyStr = '{"action":"check_access"}';

                        if ($respCheck->successful()) {
                            $accessData = $respCheck->json();
                            $status = strtoupper((string) ($accessData['status'] ?? ''));
                            $accessGranted = (bool) ($accessData['access_granted'] ?? false);
                            $memberName = $accessData['name'] ?? null;
                            $denialReason = $accessData['denial_reason'] ?? null;

                            // HANYA boleh akses jika status == ACTIVE dan access_granted == true
                            if ($status === 'ACTIVE' && $accessGranted === true) {
                                $isFoundLocally = true;
                                $this->info("✅ [Member Access Granted] {$record} ({$memberName}) status={$status} access_granted=true");
                            } else {
                                $this->warn("⛔ [Member Access Denied] {$record} ({$memberName}) status={$status} access_granted=" . ($accessGranted ? 'true' : 'false') . " reason={$denialReason}");
                                $respStatusCode = 403;
                            }
                        } else {
                            $this->warn("⚠️ [Member Check Failed] {$record} API reject: " . $respCheck->status());
                        }
                    } catch (\Exception $exMem) {
                        $this->warn("⚠️ [Member Check HTTP Err] " . $exMem->getMessage());
                        $respStatusCode = 500;
                        $respBodyStr = $exMem->getMessage();
                        $apiUrl = $checkMemberUrl;
                        $reqBodyStr = '{"action":"check_access"}';
                    }
                } else {
                    // stat=1 (OUT) → skip validasi, langsung anggap valid untuk proses check_out
                    $isFoundLocally = true;
                    $this->info("➡️ [Stat OUT] skip cek member, langsung check_out untuk {$record}");
                }

                // 2. Jika member valid & status ACTIVE & access_granted (atau stat OUT), lanjutkan proses Check-In / Check-Out
                if ($isFoundLocally) {
                    $bodyObj = [];
                    $bodyObj['member_id'] = $record;
                    $bodyObj['device_id'] = $deviceName;

                    if ($stat === 0) {
                        // Ambil URL check-in dari ApiEndpoint (ID = 19)
                        $apiUrl = $baseUrl . '/v1/check_ins';
                        try {
                            $ep19 = ApiEndpoint::find(19);
                            if ($ep19 && !empty($ep19->url)) {
                                $apiUrl = $ep19->url;
                            }
                        } catch (\Throwable $e) {}

                        $bodyObj['checked_in_at'] = now()->format('Y-m-d\TH:i:s\Z');
                    } elseif ($stat === 1) {
                        // Ambil URL check-out dari ApiEndpoint (ID = 21)
                        $apiUrl = $baseUrl . '/v1/check_outs';
                        try {
                            $ep21 = ApiEndpoint::find(21);
                            if ($ep21 && !empty($ep21->url)) {
                                $apiUrl = $ep21->url;
                            }
                        } catch (\Throwable $e) {}

                        $bodyObj['checked_out_at'] = now()->format('Y-m-d\TH:i:s\Z');
                    }

                    if ($apiUrl !== '') {
                        $reqBodyStr = json_encode($bodyObj, JSON_UNESCAPED_SLASHES);
                        try {
                            $resp = Http::withHeaders([
                                'Authorization' => 'Bearer ' . $apiKey,
                                'Accept' => 'application/json',
                                'Content-Type' => 'application/json',
                                'Connection' => 'keep-alive',
                            ])->timeout((int) config('services.fitquarters.timeout', 8))
                              ->post($apiUrl, $bodyObj);

                            $respStatusCode = $resp->status();
                            $respBodyStr = $resp->body();
                            if ($resp->successful() || $respStatusCode === 201) {
                                $isSuccess = true;
                            }
                            $this->info("🌐 [FitQuarters] {$apiUrl} | sn={$sn} member={$record} → {$respStatusCode} " . substr($respBodyStr, 0, 200));
                        } catch (\Exception $exHttp) {
                            $respBodyStr = $exHttp->getMessage();
                            $this->warn("⚠️ [FitQuarters HTTP] {$apiUrl} error: " . $respBodyStr);
                            Log::warning("[Gate sendqrcode http] sn={$sn} err: " . $exHttp->getMessage());
                        }
                    }
                } else {
                    // Jika member tidak diizinkan/invalid, simpan log URL /access ke tbl_gate_apilog
                    $apiUrl = $checkMemberUrl;
                    $reqBodyStr = '{"action":"check_access"}';
                }
            } else {
                $this->warn("⚠️ [sendqrcode] device sn={$sn} tidak ditemukan di devicegate");
            }
//jika status 0 = tidak boleh masuk
            $accessVal = $isSuccess ? 1 : 0;

            // Balas ke mesin — jika sukses result:true & access:1, jika ditolak result:false & access:0
            $reply = [
                'ret' => 'sendqrcode',
                'result' => (bool) $isSuccess,
                'access' => (int) $accessVal,
                'message' => $isSuccess ? 'SILAKAN MASUK' : 'QRCODE TIDAK DIKENALI',
            ];
            $this->sendToDeviceSocket($socket, json_encode($reply));
            $this->info("📤 [sendqrcode] sn={$sn} member={$record} stat={$stat} → result=" . ($reply['result'] ? 'true' : 'false') . " access={$accessVal} (" . $reply['message'] . ")");

            // Mirror ke access_logs agar muncul di Dashboard
            try {
                if (Schema::hasTable('access_logs')) {
                    \App\Models\AccessLog::create([
                        'door_id' => null,
                        'member_id' => $record,
                        'member_name' => $memberName,
                        'scan_type' => $stat === 1 ? 'check_out' : 'check_in',
                        'status' => $isSuccess ? 'granted' : 'denied',
                        'reason' => $isSuccess 
                            ? "QR OK via {$sn} ({$memberName})" 
                            : ($isFoundLocally 
                                ? "QR denied check_in/out ({$respStatusCode}): " . substr($respBodyStr, 0, 200) 
                                : ($denialReason ? "Ditolak: {$denialReason} ({$record})" : "Member tidak aktif/tidak valid ({$respStatusCode}): {$record}")),
                        'scanned_at' => now(),
                    ]);
                }
            } catch (\Exception $e) {}

        } catch (\Exception $ex) {
            Log::error("[Gate sendqrcode] " . $ex->getMessage());
            $this->warn("⚠️ [sendqrcode exception] " . $ex->getMessage());
            try {
                $this->sendToDeviceSocket($socket, json_encode(['ret' => 'sendqrcode', 'result' => false, 'access' => 0, 'message' => 'QRCODE TIDAK DIKENALI']));
            } catch (\Throwable $e) {}
        }
    }

    /**
     * Simpan log absensi ke database MySQL (tbl_userlog) persis seperti logika VB.NET
     */
    protected function saveAttendanceLog(array $logData): void
    {
        try {
            $sn = $logData['sn'] ?? ($logData['SN'] ?? 'UNKNOWN');
            
            $records = [];
            if (isset($logData['record']) && is_array($logData['record'])) {
                $records = $logData['record'];
            } else {
                $records = [$logData];
            }

            foreach ($records as $item) {
                $enrollId = (int) ($item['enrollid'] ?? ($item['userid'] ?? ($item['USER_ADDR'] ?? 0)));
                if ($enrollId === 0) {
                    continue;
                }

                $timeLog = $item['time'] ?? ($item['checktime'] ?? ($item['TM_EVENT'] ?? now()->format('Y-m-d H:i:s')));
                $timeFormatted = date('Y-m-d H:i:s', strtotime($timeLog));

                $pathImage = '';
                if (!empty($item['image']) && is_string($item['image'])) {
                    try {
                        $imageBytes = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $item['image']));
                        if ($imageBytes !== false) {
                            $fileName = $enrollId . '-' . date('YmdHis', strtotime($timeFormatted)) . '.jpg';
                            $targetDir = public_path('Images/Capture');
                            if (!file_exists($targetDir)) {
                                @mkdir($targetDir, 0755, true);
                            }
                            @file_put_contents($targetDir . '/' . $fileName, $imageBytes);
                            $pathImage = 'Images/Capture/' . $fileName;
                        }
                    } catch (\Exception $imgEx) {
                        Log::warning("[Gate Daemon] Gagal simpan foto capture absensi: " . $imgEx->getMessage());
                    }
                }

                $mode = (int) ($item['mode'] ?? ($item['verifytype'] ?? 0));
                $scanMethod = 'card';
                if ($mode === 8) {
                    $scanMethod = 'face';
                } elseif ($mode === 1) {
                    $scanMethod = 'finger';
                }

                // Cari konfigurasi mesin di database untuk menentukan dia IN atau OUT
                $gateDevice = \App\Models\DeviceGate::where('sn', $sn)->first();
                // Override event dari mesin fisik dengan konfigurasi stat pada sistem (0 = IN, 1 = OUT)
                $iEvent = $gateDevice ? $gateDevice->stat : (int) ($item['event'] ?? ($item['stat'] ?? 0));

                // Ambil card dari payload, jika tidak ada coba fallback Employee (jika model ada di project PCL)
                $cardValue = $item['card'] ?? ($item['cardno'] ?? '');
                if (empty($cardValue) && $enrollId > 0 && class_exists(\App\Models\Employee::class)) {
                    try {
                        $employee = \App\Models\Employee::withoutGlobalScopes()->find($enrollId);
                        if ($employee && !empty($employee->Card)) {
                            $cardValue = $employee->Card;
                        }
                    } catch (\Exception $e) {
                        // Employee model tidak dipakai di FitQuarters — abaikan
                    }
                }

                // Simpan ke tbl_userlog (raw face/finger log) — kompatibel PCL
                try {
                    DB::table('tbl_userlog')->insertOrIgnore([
                        'USER_ADDR' => $enrollId,
                        'TM_EVENT' => $timeFormatted,
                        'DEVICESN' => $sn,
                        'IMGPATH' => $pathImage,
                        'devicetype' => 1,
                        'stat' => $iEvent,
                        'scan_method' => $scanMethod,
                        'flag' => 0,
                        'card' => $cardValue,
                    ]);
                } catch (\Exception $dbEx) {
                    Log::warning("[Gate Daemon] Tabel tbl_userlog belum terbuat, bypass insert: " . $dbEx->getMessage());
                }

                // Mirror ke access_logs agar muncul di Dashboard FitQuarters (jika tabel ada)
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('access_logs')) {
                        $deviceGateForLog = $gateDevice ?? DeviceGate::where('sn', $sn)->first();
                        \App\Models\AccessLog::create([
                            'door_id' => null, // FitQuarters pakai devicegate, bukan doors
                            'member_id' => (string) $enrollId,
                            'member_name' => $item['name'] ?? null,
                            'scan_type' => $iEvent === 1 ? 'check_out' : 'check_in',
                            'status' => 'granted',
                            'reason' => "Gate {$sn} {$scanMethod} OK",
                            'scanned_at' => $timeFormatted,
                        ]);
                    }
                } catch (\Exception $e) {
                    // access_logs optional — jangan gagalkan daemon
                }

                $this->info("💾 [Log Saved] Absensi dari SN [{$sn}] | User ID: {$enrollId} | Waktu: {$timeFormatted} ({$scanMethod}) tersimpan.");
            }
        } catch (\Exception $e) {
            Log::error("[Gate Daemon] Gagal menyimpan log absensi: " . $e->getMessage());
        }
    }

    /**
     * Putuskan koneksi socket klien
     */
    protected function disconnectClient($socket, string $reason = 'unknown'): void
    {
        $id = (int) $socket;
        unset($this->clientSockets[$id]);
        unset($this->webSocketClients[$id]);
        unset($this->lastActivity[$id]);

        foreach ($this->registeredDevices as $sn => $devSocket) {
            if ($devSocket === $socket) {
                unset($this->registeredDevices[$sn]);
                $this->warn("🔴 [Device Disconnected] Mesin SN [{$sn}] terputus dari server. Alasan: {$reason}");
                
                // Auto Update status ke Disconnect (0)
                try {
                    DeviceGate::where('sn', $sn)->update(['flagstatus' => 0]);
                } catch (\Exception $e) {
                    // Ignore DB errors during disconnect
                }
                break;
            }
        }

        @fclose($socket);
    }
}
