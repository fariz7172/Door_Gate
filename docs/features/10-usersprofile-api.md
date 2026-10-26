# Fitur: UsersProfile REST API & Face Photo Engine

> **ID Fitur:** F-10  
> **Modul:** Master Personel & Biometrik Wajah  
> **Status:** Implemented & Production Ready  
> **Path:** `app/Http/Controllers/Api/UsersProfileApiController.php`  
> **Tabel Terkait:** `usersprofile`, `personal_access_tokens`, `usersxdevice`  

---

## 1. Deskripsi
Menyediakan REST API terpusat untuk sinkronisasi data personel (karyawan, anggota, tamu) ke database lokal `indonesia_defance_system`. Fitur ini mencakup logika **UPSERT**, validasi aman duplikasi kartu/NIK, otentikasi token SHA-256, serta engine pengolah foto wajah otomatis untuk hardware turnstile gate.

---

## 2. Kemampuan Utama

### A. Mekanisme UPSERT Cerdas
Pada endpoint `POST /api/usersprofile`:
- Sistem secara otomatis mencari keberadaan user berdasarkan `EMPLOYEE_ID` atau nomor `Card`.
- Jika ditemukan, sistem melakukan **UPDATE** dan mengembalikan status `HTTP 200 OK` (`action: "updated"`).
- Jika belum terdaftar, sistem melakukan **INSERT** baru dan mengembalikan status `HTTP 201 Created` (`action: "created"`).
- Menghilangkan potensi konflik error validasi `422 Unprocessable Content` saat sistem integrasi mengirim ulang data yang sama.

### B. Engine Foto Wajah (Face Terminal Photo Engine)
- Mendukung input berkas via multipart (`jpg`, `jpeg`, `png`, `webp`) maupun string base64 / data URI.
- **Standarisasi Format:** Otomatis dikonversi ke **JPEG murni** (standar hardware Soyal / Tasoft / Timmy).
- **Rentang Emas Ukuran:** Sistem memastikan ukuran file foto berada di rentang **20 KB hingga 100 KB**:
  - Jika resolusi foto terlalu kecil (< 600px), sistem melakukan upscale proporsional ke dimensi portrait badge standar (600–1000px) agar detail landmark wajah tajam.
  - Jika ukuran foto terlalu besar (> 1000px atau > 100 KB), kompresi bertingkat diterapkan hingga mencapai target ukuran ideal.
- **Public Asset CDN:** Endpoint `GET /api/usersprofile/{id}/photo` dapat diakses langsung oleh browser atau tag `<img>` tanpa memerlukan token header.

### C. Keamanan & Otentikasi API
- Dilindungi middleware `EnsureValidApiToken` (`api.token`).
- Token dienkripsi menggunakan hash SHA-256 dan dicocokkan dengan tabel `personal_access_tokens`.
- Mendukung pengiriman token melalui:
  1. Header `X-API-Key: [REDACTED]` (Sangat direkomendasikan pada lingkungan Windows CLI).
  2. Header `Authorization: Bearer [REDACTED]`.

---

## 3. Matriks Endpoint

| Method | Endpoint | Fungsi | Auth |
|---|---|---|---|
| `GET` | `/api/usersprofile` | List personel + filter & pagination | Wajib Token |
| `POST` | `/api/usersprofile` | Tambah / Update personel + Upload foto | Wajib Token |
| `GET` | `/api/usersprofile/{id}` | Detail personel | Wajib Token |
| `PUT` | `/api/usersprofile/{id}` | Update personel | Wajib Token |
| `DELETE` | `/api/usersprofile/{id?}` | Hapus personel (via ID, NIK, atau Body) | Wajib Token |
| `GET` | `/api/usersprofile/{id}/photo` | Lihat foto profil | **Bebas (Publik)** |
| `POST` | `/api/usersprofile/{id}/sync-device` | Sinkronisasi personel ke mesin gerbang | Wajib Token |

---

## 4. Pengujian & Verifikasi
- Pengujian unit dilakukan menggunakan cURL dan script PHP mandiri:
  - Create User Baru -> `HTTP 201 Created`
  - Re-POST User Sama -> `HTTP 200 OK (action: updated)`
  - Upload Foto Multipart -> Foto terkompresi ~46.7 KB (`image/jpeg`)
  - Akses Foto Langsung di Browser -> `HTTP 200 OK`
  - Hapus User via NIK -> `HTTP 200 OK`
