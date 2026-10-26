# Fitur: Real-time Access & Audit Log Dashboard
> **Status:** Planned  
> **Tanggal:** 2026-09-11  
> **Stack:** Laravel Blade, Tailwind Glass, Livewire / Polling, MySQL

## Tujuan
Memberikan dashboard admin untuk melihat siapa yang masuk/keluar secara real-time, filter berdasarkan pintu, status, dan tanggal, serta sinkronisasi dengan `GET /v1/access_events` FitQuarters.

## User Story & Acceptance Criteria
- Sebagai *Owner*, saya lihat tabel live "10 scan terakhir" auto-refresh tiap 5 detik tanpa reload.
- Sebagai *Owner*, saya bisa filter "Hari ini - Pintu Utama - Ditolak" dan export Excel.
- **AC1:** Halaman `/dashboard` menampilkan stat: total check-in hari ini, member di dalam, pintu aktif.
- **AC2:** Tabel `access_logs` pagination 20, search by `member_name`, badge hijau/merah.
- **AC3:** Tombol "Sync dari FitQuarters" tarik `GET /v1/access_events?limit=50` dan upsert.
- **AC4:** UI glass merah-putih: card `bg-white/70 backdrop-blur-xl border-red-100`.

## Alur Kerja
1. Admin buka `/dashboard` atau `/logs`.
2. Controller query `AccessLog::latest()->paginate(20)` + hitung stat agregat.
3. JS polling `fetch /api/logs/latest` tiap 5s update tabel via DOM.
4. Tombol Sync panggil `FitQuartersService::getAccessEvents()` dan merge.

## Skema DB / Model / API
- **Tabel `access_logs`:** `member_name`, `scan_type`, `status`, `reason`, `fitquarters_event_id`, `scanned_at`, `door_id FK`
- **Endpoint lokal:** `GET /api/logs/latest?limit=10`
- **Endpoint eksternal sync:** `GET /v1/access_events?limit=50`

## File yang Diubah
- `resources/views/dashboard/index.blade.php` (glass red-white)
- `resources/views/logs/index.blade.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/Api/LogController.php`
- `app/Models/AccessLog.php`
- `database/migrations/xxxx_create_access_logs_table.php`

## Cara Test & Verifikasi
- Insert 5 dummy `AccessLog` manual, buka dashboard harus tampil.
- Klik Sync -> cek `access_logs` bertambah dari API FitQuarters.
- Filter + search harus jalan, pagination tidak error.

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Inisialisasi dokumen audit log real-time |
