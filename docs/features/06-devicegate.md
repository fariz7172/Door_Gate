# Fitur: DeviceGate — Manajemen Hardware Tasoft/Soyal

> **Status:** Implemented (2026-09-11)  
> **Stack:** Laravel 11, MySQL `devicegate`, Blade + Tailwind Bespoke Glass, DeviceGatewayService (Guzzle)

## Tujuan
Menggantikan tabel `doors` legacy (relay logic) dengan tabel `devicegate` fisik — tiap baris = 1 mesin Face (type 50) / Soyal (type 1) / Finger (type 0) yang terhubung langsung ke API Tasoft (`http://localhost:787/sztimmy/*`) & Soyal (`http://localhost:8000/api/soyal/*`). Scan QR tidak lagi lewat relay dummy, tapi transaksi device asli.

## User Story & Acceptance Criteria
- Sebagai *Admin*, saya tambah device Face baru (IP 192.168.1.50, SN ZXRL23103798) dan langsung muncul di tabel untuk di-test.
- Sebagai *Admin*, saya klik icon Open / Clean Log / Cek Koneksi / Set Time / Reboot per device tanpa reload halaman, dan lihat respons JSON device di modal.
- **AC1:** Tabel `devicegate` persis spec: `id, name, number, flagstatus(0 disconnect/1 connect), type(0/50/1), sn(15), ip(20), nodeid, description(500), groupid, stat(0 IN/1 OUT), Lift1-4, locationid, timestamps`.
- **AC2:** CRUD `/devicegate` dengan validasi `name required`, `type required`, `sn/ip nullable`, `stat in 0,1`, `Lift1-4 0-255`.
- **AC3:** Index menampilkan badge type (Face violet / Soyal sky / Finger amber), stat IN/OUT, dot hijau/merah `flagstatus`, dan 6 icon aksi compact: `🔓 Open · 🧹 Clean Log · 📡 Cek Koneksi · 🕒 Set Time · 🔄 Reboot · ✏️ Edit · 🗑️ Hapus`.
- **AC4:** Tiap aksi hit `DeviceGatewayService::call(endpoint_id, {sn, Time})` dengan mapping fixed: #9 opengate `{sn}`, #10 cleanlogs `{sn}`, #11 gettime `{sn}`, #12 settime `{sn, Time: "Y-m-d H:i:s"}`, #13 reboot `{sn}`. Payload selalu pakai `sn` dari baris device.
- **AC5:** Set Time pakai modal glass — input `Y-m-d H:i:s` auto-terisi waktu sekarang, validasi `Time required`.
- **AC6:** Sidebar menu `🖧 Device Gate` dengan counter `DeviceGate::count()` (menggantikan `Pintu & Relay` sebagai menu utama).

## Alur Kerja
1. Admin buka `/devicegate` → lihat bento stats & tabel.
2. Klik `🔓` → JS `fetch POST /devicegate/{id}/open` → controller `DeviceGateController::open()` → `ApiEndpoint #9` → `POST http://localhost:787/sztimmy/opengate` body `{"sn": "ZXRL..."}` → modal `✓ BERHASIL / ✕ GAGAL` dengan `body, status, elapsed_ms`.
3. Klik `🕒` → modal Set Time → isi `2025-10-22 09:59:05` → `POST /devicegate/{id}/settime` JSON `{Time}` → hit `#12` → modal result.
4. `📡` (gettime) dipakai sebagai **cek koneksi** — jika device online akan balas timestamp; jika offline → `status 0` + error di modal.

## Skema DB / Model / API
- **Tabel `devicegate`:** lihat AC1; engine InnoDB utf8mb4_unicode_ci, AUTO_INCREMENT 25.
- **Model `App\Models\DeviceGate`:** accessors `type_label`, `type_badge`, `stat_label`, `flag_label`, `is_connected`, scopes `connected/disconnected`.
- **Service `App\Services\DeviceGatewayService::call(int $endpointId, array $payload)`:** merge `default_payload`, `withoutVerifying()` untuk http, match method GET/POST/PUT/PATCH/DELETE, return `{ok, status, elapsed_ms, body, headers, endpoint}`.
- **Controller `App\Http\Controllers\DeviceGateController`:** `index, create, store, edit, update, destroy, open, cleanLogs, getTime, setTime, reboot, enable, disable, seed`.
- **Routes:** `resource devicegate` + `POST /devicegate/{id}/{open,cleanlogs,gettime,settime,reboot,enable,disable}` + `GET /devicegate/seed`.
- **Endpoints terkait:** `api_endpoints` id 9-13 harus ada (seed via `/endpoints/seed`).

## File yang Diubah / Dibuat
- `database/migrations/2026_09_11_102638_create_devicegate_table.php`
- `app/Models/DeviceGate.php`
- `app/Services/DeviceGatewayService.php`
- `app/Http/Controllers/DeviceGateController.php`
- `resources/views/devicegate/index.blade.php` — tabel icon compact + 2 modal (result & settime) + JS fetch
- `resources/views/devicegate/form.blade.php` — hidden Lift fields
- `resources/views/layouts/app.blade.php` — sidebar `Device Gate` utama, Legacy Doors jadi kecil deprecated
- `routes/web.php`

## Cara Test & Verifikasi
- `php artisan migrate` → tabel `devicegate` ada.
- Buka `/devicegate` → 4 dummy (ZXRL23103798, ZYRL19100733, AYSK17105912, ZYSL20032920) tampil dengan icon 6 aksi.
- Klik `📡` saat Tasoft mock di `:787` mati → modal `✕ GAGAL status 0` (timeout 8s) — tidak crash.
- `POST /devicegate/{id}/settime` tanpa `Time` → 422 validation.
- `DELETE /devicegate/{id}` → confirm `Hapus device ... (SN: ...)?` → row hilang.

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Migrasi dari `doors` relay ke `devicegate` Tasoft; mapping fixed #9-13; UI icon compact + modal Set Time; SN per device; sidebar utama |
