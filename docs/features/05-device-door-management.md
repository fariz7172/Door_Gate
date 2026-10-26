# Fitur: Device & Door Multi-Terminal Management
> **Status:** Planned  
> **Tanggal:** 2026-09-11  
> **Stack:** Laravel Blade, Tailwind, MySQL

## Tujuan
Memungkinkan owner menambah, edit, nonaktifkan pintu/terminal scanner tanpa perlu ubah kode, dan mengatur arah akses (masuk/keluar/dua arah).

## User Story & Acceptance Criteria
- Sebagai *Owner*, saya tambah pintu baru "Pintu Belakang" dengan `door_code: back-gate-1` dan langsung bisa dipakai di `/kiosk/back-gate-1`.
- Sebagai *Owner*, saya bisa nonaktifkan pintu yang sedang maintenance.
- **AC1:** CRUD `/doors` dengan validasi `door_code` unique regex `^[a-z0-9-]+$`.
- **AC2:** Field: `name`, `door_code`, `direction (in/out/bidirectional)`, `relay_channel`, `pulse_duration_ms`, `is_active`.
- **AC3:** Nonaktif -> scanner tetap buka tapi scan selalu `denied` dengan reason "Door inactive".
- **AC4:** List pintu menampilkan status badge + link cepat ke Kiosk.

## Alur Kerja
1. Admin buka `/doors` -> klik Tambah Pintu.
2. Form submit `POST /doors` validasi dan insert `doors`.
3. Halaman list render card glass tiap pintu dengan tombol "Buka Kiosk" dan "Edit".
4. Scan ke pintu nonaktif ditolak sebelum hit FitQuarters API (hemat quota).

## Skema DB / Model / API
- **Tabel `doors`:** `id, door_code unique, name, direction enum, relay_channel, pulse_duration_ms, is_active bool, created_at`
- **Routes:** `resource /doors` (index, create, store, edit, update, destroy)
- **Validasi:** `door_code: required|unique:doors|regex:/^[a-z0-9-]+$/`

## File yang Diubah
- `app/Models/Door.php`
- `app/Http/Controllers/DoorController.php`
- `resources/views/doors/index.blade.php` (glass card grid)
- `resources/views/doors/form.blade.php`
- `database/migrations/xxxx_create_doors_table.php`
- `routes/web.php`

## Cara Test & Verifikasi
- CRUD manual via UI harus berhasil, `door_code` duplikat harus error.
- Akses `/kiosk/{door_code}` dengan code tidak ada -> 404.
- Nonaktifkan pintu -> scan harus `denied` tanpa hit API.

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Inisialisasi dokumen device & door management |
