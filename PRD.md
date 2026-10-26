# PRD — Indonesia Defence System: Gate Access Control & Master Personnel Gateway

> **Versi:** 2.0.0  
> **Tanggal Pembaruan:** 2026-09-23  
> **Author:** Fariz & Geta (Hermes Agent)  
> **Status:** Active / Production Ready  
> **Tech Stack:** Laravel 11, Blade, Tailwind CSS, MySQL 8.0, PHP Socket Daemon (RFC 6455 & Raw TCP), GD Image Processor  
> **Project Path:** `D:\program file\Project Kantor\indonesia defence system`  
> **Database:** `indonesia_defance_system` (MySQL Lokal)  

---

## 1. Ringkasan & Tujuan Produk

**Indonesia Defence System** adalah platform sistem kontrol akses fisik (Physical Access Control System / PACS) mandiri (*on-premise / air-gapped ready*) yang mengontrol turnstile, flap barrier, swing gate, dan magnetic lock secara terpusat untuk fasilitas perkantoran dan keamanan pertahanan.

Sistem ini sebelumnya berbasis cloud eksternal (FitQuarters Gym), namun telah **dimodifikasi secara menyeluruh menjadi sistem mandiri (standalone)**:
1. **Memutus Keterikatan Cloud Eksternal:** Validasi barcode, kartu RFID, dan biometrik wajah dialihkan **100% langsung ke database lokal** (`indonesia_defance_system`, tabel `usersprofile` & `usersxdevice`).
2. **REST API Master Personel (`/api/usersprofile`):** Menyediakan antarmuka integrasi bagi sistem pihak ketiga (HRIS / ERP / Kiosk) untuk sinkronisasi personel (karyawan, tamu, prajurit) dengan mekanisme cerdas **UPSERT (Create or Update)**.
3. **Optimasi Foto Wajah Standar Mesin:** Fitur kompresi otomatis berkas foto yang di-upload via multipart ke format **JPEG berkualitas tinggi dalam rentang emas 20 KB – 100 KB** (resolusi 600–1000px) untuk memastikan akurasi deteksi algoritma wajah pada terminal hardware.
4. **Keamanan API:** Dilindungi dengan header otentikasi `X-API-Key` dan `Bearer Token` menggunakan enkripsi hash SHA-256.

---

## 2. Arsitektur Port & Service

Aplikasi berjalan dengan arsitektur multi-port terkoordinasi:

| Port | Protokol | Komponen / Service | Keterangan |
|---|---|---|---|
| **`8090`** | HTTP | Laravel Web & REST API | Server aplikasi utama (Admin Dashboard, CRUD API, Photo asset) |
| **`7788`** | Raw TCP & WebSocket | Hardware Socket Listener | Menerima koneksi socket persisten dari mesin gate fisik (Tasoft / Soyal / Timmy) |
| **`787`** | HTTP REST | Daemon Command Dispatcher | Menerima perintah trigger lokal (`/sztimmy/*`) seperti `registerface`, `opengate`, `settime` |

---

## 3. Fitur Utama & Modul

| Kode | Modul / Fitur | Status | Keterangan |
|---|---|---|---|
| **F-01** | **Master Personel REST API (`/api/usersprofile`)** | ✅ Aktif | CRUD lengkap + UPSERT cerdas (berdasarkan `EMPLOYEE_ID` atau `Card`). |
| **F-02** | **Otentikasi API Token (`X-API-Key`)** | ✅ Aktif | Proteksi endpoint via SHA-256 token hash di tabel `personal_access_tokens`. |
| **F-03** | **Hardware Socket Daemon (`gate:serve`)** | ✅ Aktif | Daemon port 7788 (TCP) & port 787 (HTTP) di `GateServerCommand.php`. |
| **F-04** | **Face Terminal Photo Engine** | ✅ Aktif | Auto-compress & upscale foto ke JPEG [20 KB – 100 KB] untuk terminal pengenal wajah. |
| **F-05** | **Public Photo CDN Endpoint** | ✅ Aktif | `GET /api/usersprofile/{id}/photo` dapat diakses langsung tanpa token untuk tag `<img>`. |
| **F-06** | **Sinkronisasi Wajah Mesin (`sync-device`)** | ✅ Aktif | `POST /api/usersprofile/{id}/sync-device` untuk inject wajah & hak akses ke gate fisik. |
| **F-07** | **Master Cabang (`branch`) & Divisi (`departemen`)** | ✅ Aktif | Pengelompokan departemen dan multi-cabang kantor. |
| **F-08** | **Device Gate Management (`devicegate`)** | ✅ Aktif | Manajemen IP, Serial Number (SN), dan status konektivitas mesin pintu fisik. |
| **F-09** | **Audit Log Akses (`tbl_userlog` & `access_logs`)** | ✅ Aktif | Pencatatan tap kartu, scan barcode, dan deteksi wajah secara real-time. |

---

## 4. Spesifikasi REST API Master Personel (`/api/usersprofile`)

### 4.1 Header Request Wajib
Untuk seluruh endpoint yang diproteksi, sertakan header berikut:
```http
X-API-Key: [REDACTED_API_TOKEN]
Accept: application/json
```
*(Catatan: Token juga dapat dikirim via `Authorization: Bearer [REDACTED]`)*

### 4.2 Daftar Endpoint

#### A. Tambah atau Perbarui User (UPSERT)
- **Method:** `POST`
- **URL:** `http://localhost:8090/api/usersprofile`
- **Content-Type:** `multipart/form-data` (disarankan) atau `application/json`
- **Aturan Cerdas:**
  - Jika `EMPLOYEE_ID` atau `Card` belum ada di database, sistem melakukan **CREATE** (`HTTP 201 Created`).
  - Jika `EMPLOYEE_ID` atau `Card` sudah terdaftar, sistem melakukan **UPDATE** data tersebut (`HTTP 200 OK`). Tidak akan terjadi error duplikat 422!

**Parameter Form-Data / JSON:**
| Field | Tipe | Status | Keterangan |
|---|---|---|---|
| `EMPLOYEE_ID` | String (max:15) | Wajib | Nomor Induk Karyawan / Personel (NIK) |
| `NAME` | String (max:100) | Wajib | Nama Lengkap |
| `LAST_NAME` | String (max:50) | Opsional | Nama Belakang / Panggilan |
| `Card` | String (max:15) | Opsional | Nomor Kartu RFID / Barcode akses |
| `NoIdentitas` | String (max:30) | Opsional | Nomor KTP / Paspor / SIM |
| `user_type` | Integer | Opsional | `1` = Member/Tamu, `2` = Karyawan/Personel (Default: 2) |
| `is_active` | Integer | Opsional | `1` = Aktif, `0` = Non-aktif (Default: 1) |
| `JOB_POSITION`| String (max:30) | Opsional | Jabatan / Pangkat |
| `Depid` | Integer | Opsional | ID Departemen (relasi ke tabel `departemen`) |
| `Branchid` | Integer | Opsional | ID Cabang (relasi ke tabel `branch`) |
| `BEGIN_DATE` | Datetime | Opsional | Masa berlaku mulai (Format: `YYYY-MM-DD HH:mm:ss`) |
| `END_DATE` | Datetime | Opsional | Masa berlaku habis (Default: `2079-12-31 23:59:59`) |
| `photo` | File / String | Opsional | Berkas foto JPG/PNG/WebP atau string base64. Otomatis dikompresi ke JPEG [20 KB – 100 KB]. |

#### B. Mengambil Daftar User (List & Search)
- **Method:** `GET`
- **URL:** `http://localhost:8090/api/usersprofile`
- **Query Params:**
  - `q` : Pencarian nama / NIK / Kartu
  - `user_type` : Filter tipe personel (`1` atau `2`)
  - `is_active` : Filter status (`1` atau `0`)
  - `per_page` : Jumlah data per halaman (default: 20)

#### C. Detail User
- **Method:** `GET`
- **URL:** `http://localhost:8090/api/usersprofile/{id}`

#### D. Menghapus User (Flexible DELETE)
Mendukung 3 cara penghapusan:
1. **Via URL NIK:** `DELETE http://localhost:8090/api/usersprofile/EMP-9002` (Tanpa body)
2. **Via URL ID:** `DELETE http://localhost:8090/api/usersprofile/27` (Tanpa body)
3. **Via Body JSON:** `DELETE http://localhost:8090/api/usersprofile` dengan body `{"EMPLOYEE_ID": "EMP-9002"}`

#### E. Mengakses Foto Profil (Public Asset)
- **Method:** `GET`
- **URL:** `http://localhost:8090/api/usersprofile/{id}/photo`
- **Akses:** Bebas tanpa token (bisa dibuka langsung di browser tab atau tag `<img src="...">`).

---

## 5. Skema Tabel Utama MySQL (`indonesia_defance_system`)

### 5.1 Tabel `usersprofile`
Menyimpan master data seluruh anggota dan personel:
- `ID` (int PK AUTO_INCREMENT)
- `EMPLOYEE_ID` (varchar 15, unique)
- `NAME` (varchar 100)
- `LAST_NAME` (varchar 50, nullable)
- `BEGIN_DATE` & `END_DATE` (datetime masa aktif akses)
- `Depid` & `Branchid` (FK relasi struktur organisasi)
- `photo` (longblob, foto terkompresi JPEG 20–100 KB)
- `Card` (varchar 15, nomor kartu RFID/turnstile)
- `NoIdentitas` (varchar 30)
- `user_type` (tinyint: 1 Member/Tamu, 2 Karyawan)
- `is_active` (tinyint: 1 Aktif, 0 Non-aktif)
- `JOB_POSITION` (varchar 30)
- `created_at` & `updated_at` (datetime)

### 5.2 Tabel `personal_access_tokens`
Menyimpan hash token API dengan keamanan standar SHA-256:
- `id` (bigint PK)
- `tokenable_type` & `tokenable_id` (Relasi user)
- `name` (Nama token, misal: "API Production")
- `token` (char 64, hash sha256 dari plain token)
- `abilities` (text, scope permission)
- `last_used_at` & `expires_at`

### 5.3 Tabel `devicegate`
Menyimpan daftar mesin pintu turnstile / face terminal:
- `id` (int PK)
- `name` (Nama pintu, cth: "Gate Masuk - Utama")
- `sn` (Serial Number mesin, cth: "AYSG15085238")
- `ip` (Alamat IP mesin di LAN, cth: `192.168.100.224`)
- `port` (Port komunikasi, default 7788)
- `stat` (Status arah: 0 Masuk, 1 Keluar)
- `is_active` (tinyint: 1 Aktif, 0 Nonaktif)

---

## 6. Prosedur Operasional & Perintah Konsol

### 6.1 Menghasilkan API Token Baru
Untuk membuat token baru tanpa perlu login UI:
```bash
php artisan token:create 1 "Nama Aplikasi Klien" --days=365
```

### 6.2 Menjalankan Server
1. **Server Aplikasi Web & REST API:**
   ```bash
   php artisan serve --port=8090
   ```
   *(Atau jalankan `start-app.bat`)*

2. **Server Hardware Socket Gate Daemon:**
   ```bash
   php artisan gate:serve
   ```

---

## 7. Riwayat Pembaruan Dokumen (Changelog)

| Versi | Tanggal | Deskripsi Pembaruan |
|---|---|---|
| **2.0.0** | 2026-09-23 | **Transformasi Menyeluruh ke Indonesia Defence System:**<br>• Memutus keterikatan dari cloud FitQuarters; validasi akses dialihkan ke database lokal.<br>• Implementasi REST API `/api/usersprofile` dengan mekanisme UPSERT cerdas.<br>• Penambahan engine kompresi foto otomatis (JPEG 20 KB – 100 KB).<br>• Pengamanan API berbasis SHA-256 token (`X-API-Key`).<br>• Pembukaan akses publik untuk endpoint view photo. |
| **1.1.0** | 2026-09-11 | Penyesuaian arsitektur hardware gate Tasoft/Soyal & socket daemon port 7788/787. |
| **1.0.0** | 2026-09-11 | Rilis awal dokumen sistem gateway pintu. |
