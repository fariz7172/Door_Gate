# Fitur: Gate Server Socket Daemon — Dual-Port Bridge (Hardware & API)

> **Status:** Implemented (2026-09-11)  
> **Stack:** Laravel Artisan Command (`php artisan gate:serve`), Stream Sockets (`tcp://0.0.0.0`), WebSocket (RFC 6455), HTTP 1.1 Server, MySQL `devicegate` & `tbl_userlog` & `access_logs`

## Tujuan
Menyediakan server socket daemon mandiri berkecepatan tinggi yang berjalan 24/7 di lokal gym untuk:
1. Menampung koneksi TCP / WebSocket persisten dari mesin gerbang/turnstile fisik (Tasoft Face Terminal, Fingerprint, Soyal).
2. Membuka server HTTP lokal (`http://localhost:787/sztimmy/*`) agar controller web Laravel / AJAX / service gateway dapat mengirim instruksi real-time ke mesin fisik berdasarkan Serial Number (`sn`).
3. Menerima event absensi/check-in (`sendlog`, `newlog`, `realtime_log`) beserta foto capture Base64 dan menyimpannya secara atomik ke database.

---

## Arsitektur Dual-Port

```
[ Mesin Turnstile / Face Tasoft ]
          │ (TCP / WebSocket persistent)
          ▼
   [ Port 7788 : Hardware Listener ]
          │
          │ ─── GateServerCommand.php ───
          │        (stream_select loop)
          ▲
   [ Port 787  : HTTP API sztimmy ]
          ▲
          │ (HTTP POST /sztimmy/opengate, /cleanlogs, /gettime, /settime, /reboot, dll)
[ DeviceGatewayService / Controller Web ]
```

- **Port 7788 (Hardware Socket Server):**
  - Menerima koneksi raw TCP atau upgrade WebSocket (`/pub/chat` dengan header `Upgrade: websocket` dan validasi handshake RFC 6455).
  - Melacak socket mesin aktif dalam memori (`$registeredDevices[$sn] = $socket`).
  - Saat mesin kirim handshake registrasi `{"cmd": "reg", "sn": "..."}`, server membalas konfirmasi `cloudtime` dan otomatis mengubah status database `devicegate.flagstatus = 1` (Connect).
  - Saat socket putus (`feof` / close frame opcode `8`), server otomatis update `devicegate.flagstatus = 0` (Disconnect).

- **Port 787 (HTTP API Gateway sztimmy):**
  - Menerima request HTTP dari web controller / Postman / cURL.
  - Membaca request body JSON (mendukung `Expect: 100-continue` dan buffer drain hingga 64KB untuk data besar).
  - Memetakan URL path ke perintah JSON hardware dan meneruskannya langsung ke socket mesin yang cocok dengan `sn`.
  - Mengembalikan respons JSON HTTP 200 OK jika perintah terkirim, 404 jika mesin offline, atau 400 jika SN tidak ditemukan.

---

## Pemetaan Endpoint HTTP ke Perintah Mesin

| Path Endpoint (`localhost:787`) | Method | Payload Masuk | Perintah Terkirim ke Hardware (Port 7788) |
|---|---|---|---|
| `/sztimmy/opengate`, `/opendoor` | POST | `{"sn": "..."}` | `{"cmd": "opendoor", "doornum": 1}` |
| `/sztimmy/cleanlogs`, `/cleanlog` | POST | `{"sn": "..."}` | `{"cmd": "cleanlog"}` |
| `/sztimmy/gettime` | POST | `{"sn": "..."}` | `{"cmd": "gettime"}` |
| `/sztimmy/settime` | POST | `{"sn": "...", "Time": "Y-m-d H:i:s"}` | `{"cmd": "settime", "time": "...", "cloudtime": "..."}` |
| `/sztimmy/reboot` | POST | `{"sn": "..."}` | `{"cmd": "reboot"}` |
| `/sztimmy/enabledevice` | POST | `{"sn": "..."}` | `{"cmd": "enabledevice"}` |
| `/sztimmy/disabledevice` | POST | `{"sn": "..."}` | `{"cmd": "disabledevice"}` |
| `/sztimmy/getuserlist` | POST | `{"sn": "..."}` | `{"cmd": "getuserlist", "stn": true}` |
| `/sztimmy/registerface`, `/registerfull` | POST | `{"sn": "...", "userid": 1, "fdata": "..."}` | `{"cmd": "setuserinfo", "enrollid": 1, "record": "..."}` |
| `/sztimmy/deleteface`, `/deleteuser` | POST | `{"sn": "...", "userid": 1}` | `{"cmd": "deleteuser", "enrollid": 1, "backupnum": 13}` |
| `/sztimmy/dayzone` | POST | `{"sn": "...", "Day1": "08:00~22:00"}` | `{"cmd": "setdevlock", "dayzone": [...]}` |
| `/sztimmy/weekzone` | POST | `{"sn": "...", "Week1": "1;1;1;1;1;1;1"}` | `{"cmd": "setdevlock", "weekzone": [...]}` |
| `/sztimmy/userweekzone` | POST | `{"sn": "...", "userid": 1, "weekzone": 1}` | `{"cmd": "setuserlock", "count": 1, "record": [...]}` |

---

## Logika Pemrosesan Log & Dual-Write Database

Ketika mesin gerbang mengirim data log absensi (`sendlog` / `newlog` / `realtime_log`):
1. **Penyimpanan Gambar:** Jika payload menyertakan data foto Base64 (`image`), file di-decode dan disimpan ke direktori `public/Images/Capture/{enrollId}-{timestamp}.jpg`.
2. **Tabel `tbl_userlog`:** Menyimpan log presisi level hardware (`USER_ADDR`, `TM_EVENT`, `DEVICESN`, `IMGPATH`, `devicetype`, `stat`, `scan_method`, `card`).
3. **Tabel `access_logs`:** Dual-write otomatis untuk menyinkronkan event ke antarmuka Dashboard FitQuarters (`member_id`, `scan_type` IN/OUT dari `devicegate.stat`, `status: granted`, `scanned_at`).
4. **Acknowledgement:** Server membalas ke mesin dengan JSON `{"ret": "sendlog", "result": true, "access": 1}` agar buffer memori lokal mesin tidak menumpuk.

---

## Cara Menjalankan Server Daemon

Jalankan perintah ini di jendela terminal / Command Prompt terpisah:

```bash
cd "D:\program file\Project Kantor\indonesia defence system"

# Port default (7788 untuk hardware, 787 untuk HTTP API)
"C:/xampp/php/php.exe" artisan gate:serve

# Atau menggunakan custom port
"C:/xampp/php/php.exe" artisan gate:serve --port=7788 --api-port=787
```

Saat pertama kali dijalankan, server akan:
1. Mereset seluruh status mesin di tabel `devicegate` menjadi `flagstatus = 0` (mencegah status online palsu jika ada mesin yang mati saat server offline).
2. Membuka listener `tcp://0.0.0.0:7788` dan `tcp://0.0.0.0:787` dalam mode non-blocking `stream_select`.

---

## File Terkait
- `app/Console/Commands/GateServerCommand.php` — Kernel daemon socket & HTTP dispatcher.
- `database/migrations/2026_09_11_113000_create_tbl_userlog_table.php` — Skema tabel log hardware.
- `app/Models/DeviceGate.php` — Model tabel `devicegate`.
- `app/Models/AccessLog.php` — Model tabel audit log dashboard.
- `app/Services/DeviceGatewayService.php` — Client HTTP internal penghubung Blade UI ke port 787.

---

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Porting dan adaptasi `GateServerCommand` dari PCL ke FitQuarters Gym; integrasi model `DeviceGate`, migrasi `tbl_userlog`, dual-write `access_logs`, support WebSocket RFC 6455 & HTTP 787 sztimmy |
