# Fitur: Departemen — Master Divisi

> **Status:** Implemented (2026-09-11)  
> **Stack:** Laravel 11, MySQL `departemen`, Blade Bespoke Glass

## Tujuan
Master data departemen/divisi (Trainer, Front Office, Maintenance, dll) dipakai sebagai DeptCode (`number`) & DeptName (`name`) untuk grouping karyawan/member dan referensi `devicegate.groupid`.

## User Story & Acceptance Criteria
- Sebagai *Admin*, saya tambah departemen `D01 - Trainer & Coach` dan langsung tampil di index.
- **AC1:** Tabel `departemen` persis spec: `id, number varchar(15) default '' INDEX DeptCode, name varchar(30) default '' INDEX DeptName, description varchar(100) default ''` — InnoDB utf8mb4, AUTO_INCREMENT 23.
- **AC2:** CRUD `/departemen` dengan validasi `number max 15`, `name max 30`, `description max 100` (semua nullable — default '' sesuai spec lama).
- **AC3:** Index: badge DeptCode bulat hitam, name bold, description truncate, search `number/name/description`, pagination 12, aksi `✏️ Edit · 🗑️ Hapus`.
- **AC4:** Form hint 15/30/100, placeholder `D01`, `Trainer & Coach`.
- **AC5:** Sidebar `🗂️ Departemen` dengan counter `Departemen::count()`. Route param `departeman` (singular Laravel untuk `departemen` → `parameters: departeman`).

## Alur Kerja
1. Admin buka `/departemen` → + Tambah Departemen.
2. Isi DeptCode/DeptName/Deskripsi → `POST /departemen` → validasi → `✓ Departemen ditambahkan`.
3. Search `?search=trainer` → filter LIKE across 3 kolom.
4. Edit `PUT /departemen/{id}` / Delete `DELETE` dengan confirm.

## Skema DB / Model / API
- **Tabel `departemen`:** lihat AC1; KEY `DeptCode(number)`, KEY `DeptName(name)`.
- **Model `App\Models\Departemen`:** `table departemen`, `fillable number,name,description`.
- **Controller `App\Http\Controllers\DepartemenController`:** `index, create, store, edit, update, destroy` — binding `Departemen $departeman`.
- **Routes:** `Route::resource('departemen', DepartemenController::class)->parameters(['departemen'=>'departeman'])`.

## File yang Diubah / Dibuat
- `database/migrations/2026_09_11_111859_create_departemen_table.php`
- `app/Models/Departemen.php`
- `app/Http/Controllers/DepartemenController.php`
- `resources/views/departemen/index.blade.php`
- `resources/views/departemen/form.blade.php`
- `resources/views/layouts/app.blade.php` — sidebar Departemen
- `routes/web.php`

## Cara Test & Verifikasi
- `php artisan migrate` → `departemen` ada, `SHOW INDEX FROM departemen` ada `DeptCode` & `DeptName`.
- `POST /departemen` number `D01` duplikat → lolos (tidak unique — sesuai spec lama yang hanya index biasa).
- `GET /departemen?search=D01` → filter benar.
- `DELETE /departemen/1` → row hilang.

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Inisialisasi master departemen sesuai spec; CRUD + search + sidebar |
