# Fitur: Branch — Master Cabang

> **Status:** Implemented (2026-09-11)  
> **Stack:** Laravel 11, MySQL `branch`, Blade Bespoke Glass

## Tujuan
Master data cabang gym (FitQuarters Pusat, cabang daerah, dll) dipakai sebagai `branchCode` (field `number` unique) untuk grouping device, member, dan laporan.

## User Story & Acceptance Criteria
- Sebagai *Admin*, saya tambah cabang baru (number 1, name "FitQuarters Pusat") dan bisa cari via search.
- **AC1:** Tabel `branch` persis spec: `id, number int unique (branchCode), name varchar(30), description varchar(100), timestamps` — InnoDB utf8mb4, AUTO_INCREMENT 17.
- **AC2:** CRUD `/branch` dengan validasi `number nullable|integer|unique:branch,number`, `name max 30`, `description max 100`.
- **AC3:** Index: tabel badge `number` bulat hitam, kolom name bold, description truncate, aksi `✏️ Edit · 🗑️ Hapus` icon, search `number/name/description`, pagination 12, glass rounded-[24px].
- **AC4:** Form max-length hint 30/100, `number` unique error jika duplikat.
- **AC5:** Sidebar `🏢 Branch` dengan counter `Branch::count()`.

## Alur Kerja
1. Admin buka `/branch` → klik + Tambah Branch.
2. Isi number/name/description → `POST /branch` → `branch.store` validasi → redirect index `✓ Cabang ditambahkan`.
3. Search `?search=pusat` → filter `WHERE name/description/number LIKE`.
4. Edit/Hapus per baris → `PUT /branch/{id}` / `DELETE`.

## Skema DB / Model / API
- **Tabel `branch`:** lihat AC1; UNIQUE `branchCode(number)`.
- **Model `App\Models\Branch`:** `fillable number,name,description`, `casts number=>integer`.
- **Controller `App\Http\Controllers\BranchController`:** `index(create search), create, store, edit, update, destroy`.
- **Routes:** `Route::resource('branch', BranchController::class)` → 7 routes `branch.*`.

## File yang Diubah / Dibuat
- `database/migrations/2026_09_11_111337_create_branch_table.php`
- `app/Models/Branch.php`
- `app/Http/Controllers/BranchController.php`
- `resources/views/branch/index.blade.php`
- `resources/views/branch/form.blade.php`
- `resources/views/layouts/app.blade.php` — sidebar Branch
- `routes/web.php`

## Cara Test & Verifikasi
- `php artisan migrate` → `branch` ada, `SHOW CREATE TABLE branch` cek UNIQUE `number`.
- `POST /branch` number duplikat → 422 `unique`.
- Search `?search=1` → hanya branch number 1 tampil.
- `DELETE /branch/1` → confirm → row hilang, counter sidebar berkurang.

## Changelog Fitur
| Tanggal | Perubahan |
|---|---|
| 2026-09-11 | Inisialisasi master branch sesuai spec; CRUD + search + sidebar |
