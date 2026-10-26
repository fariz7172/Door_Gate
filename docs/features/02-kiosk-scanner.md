# Fitur: Kiosk Door Scanner UI (Bespoke Glass Red-White)
> **Status:** Planned  
> **Tanggal:** 2026-09-11  
> **Stack:** Laravel Blade, Tailwind CSS, html5-qrcode, Web Speech API

## Tujuan
Menyediakan halaman fullscreen khusus tablet di depan pintu untuk scan barcode/QR member secara cepat, dengan feedback visual glass merah-putih dan audio yang jelas agar member tahu pintu terbuka atau ditolak.

## User Story & Acceptance Criteria
- Sebagai *Member*, saya tempelkan barcode dan dalam 1 detik saya tahu apakah boleh masuk (layar hijau) atau ditolak (layar merah).
- Sebagai *Staff*, saya bisa lihat preview kamera dan status koneksi API di pojok layar scanner.
- **AC1:** Halaman `/kiosk/{door_code}` fullscreen, tanpa sidebar, auto-focus scanner.
- **AC2:** Scan sukses -> overlay glass hijau `#10B981` + suara "Silakan Masuk" + nama member.
- **AC3:** Scan ditolak -> overlay glass merah `#DC2626` + suara "Akses Ditolak" + alasan.
- **AC4:** Desain glass: `bg-white/75 backdrop-blur-[20px] border border-red-200/40` + gradasi merah `from-red-600 to-red-800`.

## Alur Kerja
1. User buka `/kiosk/front-gate-1` di tablet (kiosk mode).
2. JS `html5-qrcode` aktifkan kamera belakang, tampilkan viewfinder kotak merah gradasi.
3. Saat barcode terdeteksi, JS `fetch POST /api/scan` kirim `member_id`.
4. Response `granted/denied` render overlay glass + play audio + trigger relay (via backend).
5. Auto-reset setelah 3 detik siap scan berikutnya.

## Skema DB / Model / API
- **Route:** `GET /kiosk/{door_code}` -> `KioskController@show`, `POST /api/scan` -> `ScanController@store`
- **Payload scan:** `{door_code: string, member_id: string, type: "in"|"out"}`
- **Response:** `{status: "granted"|"denied", member_name, reason, event_id}`

## File yang Diubah
- `resources/views/kiosk/scanner.blade.php` (baru, fullscreen glass red-white)
- `resources/views/layouts/kiosk.blade.php` (layout tanpa sidebar)
- `public/js/kiosk-scanner.js` (baru)
- `app/Http/Controllers/KioskController.php`
- `routes/web.php` & `routes/api.php`

## Cara Test & Verifikasi
- Buka `http://localhost:8000/kiosk/front-gate-1` di tablet/HP, scan barcode dummy `01a08a2b-...` harus muncul overlay.
- Test audio autoplay policy (harus ada interaksi user pertama).
- `php artisan serve` + cek Tailwind glass blur 18-22px render benar.

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Inisialisasi dokumen kiosk scanner glass merah-putih |
