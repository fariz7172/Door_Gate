# Door_Gate — Access Control & Hardware Gate Gateway

Aplikasi mandiri (*on-premise / private network ready*) berbasis **Laravel 11** untuk kontrol akses fisik (turnstile, flap barrier, magnetic lock) dan manajemen data master personel.

---

## 🚀 Ringkasan Layanan & Port

| Port | Service | Fungsi |
|---|---|---|
| **`8090`** | **Laravel Web & REST API** | Dashboard admin, endpoint CRUD personel, dan view photo asset. |
| **`7788`** | **Socket Listener (TCP / WS)** | Listener koneksi persisten hardware mesin gate (Soyal / Tasoft / Timmy). |
| **`787`** | **HTTP Daemon Dispatcher** | Trigger hardware internal (`/sztimmy/*`: `registerface`, `opengate`, dll). |

---

## ⚙️ Menjalankan Aplikasi

### 1. Menjalankan Sekaligus (Rekomendasi)
Jalankan file batch:
```cmd
start-app.bat
```

### 2. Menjalankan Manual per Service
**Terminal 1 (Web & REST API):**
```bash
php artisan serve --port=8090
```

**Terminal 2 (Socket Gate Daemon):**
```bash
php artisan gate:serve
```

---

## 🔑 Otentikasi API

Semua request ke endpoint `/api/usersprofile/*` (kecuali foto) wajib menyertakan salah satu header berikut:

```http
X-API-Key: [REDACTED_API_TOKEN]
Accept: application/json
```
*Atau:*
```http
Authorization: Bearer [REDACTED_API_TOKEN]
Accept: application/json
```

### Membuat Token Baru:
```bash
php artisan token:create 1 "Nama Aplikasi Klien" --days=365
```

---

## 📡 Dokumentasi Endpoint REST API

### 1. Tambah / Perbarui Personel (UPSERT Tunggal)
- **URL:** `POST http://localhost:8090/api/usersprofile`
- **Headers:** `X-API-Key`, `Accept: application/json`
- **Body:** `form-data` (disarankan) atau `application/json`

| Key | Tipe | Keterangan |
|---|---|---|
| `EMPLOYEE_ID` | Text | Nomor Induk Personel / Karyawan (Wajib) |
| `NAME` | Text | Nama Lengkap (Wajib) |
| `LAST_NAME` | Text | Nama Belakang / Panggilan |
| `Card` | Text | Nomor Kartu RFID / Barcode Turnstile |
| `user_type` | Text | `2` = Personel/Karyawan, `1` = Tamu/Member |
| `is_active` | Text | `1` = Aktif, `0` = Non-aktif |
| `JOB_POSITION`| Text | Pangkat / Jabatan |
| `photo` | **File** | Berkas foto JPG, JPEG, PNG, atau WebP. Otomatis dioptimasi ke **JPEG [20 KB – 100 KB]**. |

> 💡 **Sifat UPSERT:** Jika `EMPLOYEE_ID` atau `Card` belum ada, sistem membuat data baru (`201 Created`). Jika sudah ada, sistem otomatis meng-update data lama (`200 OK`).

---

### 1B. Bulk Import / Sinkronisasi Ribuan Data (Chunked Batch)
- **URL:** `POST http://localhost:8090/api/usersprofile/bulk` *(atau tembak ke `/api/usersprofile` dengan array)*
- **Headers:** `X-API-Key`, `Content-Type: application/json`, `Accept: application/json`
- **Body JSON:**
  ```json
  {
      "chunk_size": 50,
      "users": [
          { "EMPLOYEE_ID": "EMP-001", "NAME": "Budi", "Card": "1001", "user_type": 2 },
          { "EMPLOYEE_ID": "EMP-002", "NAME": "Tarman", "Card": "1002", "user_type": 2 }
      ]
  }
  ```
  *(Atau langsung kirim array: `[ {"EMPLOYEE_ID": "..."}, ... ]`)*

> ⚡ **Keunggulan Sistem Chunking:**
> - Server membagi data per 50 record (bisa diatur via `chunk_size`).
> - Menggunakan **Database Transaction per Chunk** + **Single Batch Query** (bebas N+1 bottleneck).
> - Garbage Collector membebaskan RAM setiap chunk selesai, sehingga server **ringan, stabil, dan tidak akan kehabisan memori walau memproses ribuan data**!
> - Kecepatan: 120 record selesai dalam ~0.3 detik!

---

### 2. Mengambil Daftar Personel
- **URL:** `GET http://localhost:8090/api/usersprofile`
- **Query Params:**
  - `?q=budi` (Pencarian nama / NIK / Kartu)
  - `?is_active=1` (Hanya personel aktif)
  - `?page=1&per_page=20` (Paginasi)

---

### 3. Detail Personel
- **URL:** `GET http://localhost:8090/api/usersprofile/{id}`

---

### 4. Menghapus Personel Tunggal (Flexible DELETE)
Mendukung 3 format:
- `DELETE http://localhost:8090/api/usersprofile/EMP-9002` *(Berdasarkan NIK)*
- `DELETE http://localhost:8090/api/usersprofile/27` *(Berdasarkan ID)*
- `DELETE http://localhost:8090/api/usersprofile` *(Dengan Body JSON: `{"EMPLOYEE_ID": "EMP-9002"}`)*

---

### 4B. Bulk Delete Ribuan Data (Chunked Batch)
- **URL:** `DELETE http://localhost:8090/api/usersprofile/bulk` *(atau ke `/api/usersprofile`)*
- **Headers:** `X-API-Key`, `Content-Type: application/json`, `Accept: application/json`
- **Body JSON:**
  ```json
  {
      "chunk_size": 100,
      "employee_ids": ["EMP-001", "EMP-002", "EMP-003"]
  }
  ```
  *(Mendukung juga kunci `"ids": [1, 2, 3]`, `"cards": ["1001", "1002"]`, atau array string murni)*

> ⚡ **Keunggulan Bulk Delete:**
> - Menghapus ratusan hingga ribuan user dan relasi mesin gerbang (`usersxdevice`) secara atomik.
> - Dipecah per chunk (default: 100) dengan Garbage Collection otomatis.
> - Kecepatan: 150 user terhapus bersih dalam **0.06 detik**!

---

### 5. Melihat Foto Profil (Public Asset)
- **URL:** `GET http://localhost:8090/api/usersprofile/{id}/photo`
- **Akses:** Terbuka tanpa token (bisa dibuka langsung di tab browser atau dipasang di tag HTML `<img src="...">`).

---

### 6. Sinkronisasi Data Wajah ke Mesin Gate
- **URL:** `POST http://localhost:8090/api/usersprofile/{id}/sync-device`
- **Body JSON:** `{"gate_id": 1}`

---

## ⏰ Otomatisasi Sinkronisasi ke Gerbang (Setiap 5 Menit)

Tersedia Artisan Command cerdas:
```bash
# Jalankan sekali pemeriksaan:
php artisan users:sync-gate

# Jalankan terus-menerus setiap 5 menit (Daemon Mode):
php artisan users:sync-gate --loop

# Opsi khusus (hanya mesin SN tertentu atau paksa semua):
php artisan users:sync-gate --sn=ZXRL23103798 --force
```

### Logika Kerja Cerdas:
1. **Pre-Flight Device Check (Cek Status Mesin di Awal):**
   - Sebelum memproses ribuan personel, sistem menanyakan status mesin aktif ke socket port 787 (`GET /sztimmy/active-devices`).
   - **Jika semua mesin offline:** Sinkronisasi ditunda otomatis ke 5 menit berikutnya, dan `updated_at` **TIDAK diubah**. Server tidak akan membuang resource CPU sia-sia.
   - **Hanya mesin yang AKTIF** yang akan dikirimi data. Mesin yang sedang offline otomatis di-skip.
2. **Teknik Chunking Bertahap (`chunkById`):**
   - Data diproses bertahap (default 50 user per chunk, opsi `--chunk=100`).
   - Setiap batch selesai, RAM langsung dibebaskan via `gc_collect_cycles()`.
3. **Kondisi Sinkronisasi:**
   `api_last_update > updated_at` **ATAU** `updated_at IS NULL`.
4. **Menembak Endpoint Daemon Mesin:**
   - `POST http://localhost:787/sztimmy/registerface` (Data biometrik wajah & kartu).
   - `POST http://localhost:787/sztimmy/useraccess` (Zona waktu & masa berlaku akses).
5. **Auto-Retry & Safe Timestamp:**
   Nilai `updated_at` **hanya diperbarui jika data benar-benar sukses diterima mesin yang aktif**. Jika pengiriman gagal/tertunda, data tetap aman berada di antrean untuk dicoba kembali pada siklus 5 menit berikutnya!
6. **Terjadwal Otomatis di `routes/console.php`:**
   ```php
   Schedule::command('users:sync-gate')->everyFiveMinutes();
   ```
7. **Otomatis Berjalan di Background:**
   Saat `start-app.bat` diklik, background daemon ini ikut berjalan senyap di tray!

---

## 🗄️ Database
- **Engine:** MySQL 8.0
- **Nama DB:** `indonesia_defance_system`
- **Tabel Kunci:** `usersprofile`, `usersxdevice`, `personal_access_tokens`, `devicegate`, `tbl_userlog`, `access_logs`, `branch`, `departemen`.

---
*Diperbarui: 23 September 2026 — Indonesia Defence System Project Kantor.*
