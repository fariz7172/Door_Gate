<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsersProfile;
use App\Models\UsersXDevice;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class UsersProfileApiController extends Controller
{
    /* ================= GET /api/usersprofile ================= */
    public function index(Request $r): JsonResponse
    {
        $q = UsersProfile::query()->with(['branch:id,name', 'departemen:id,name']);

        if ($r->filled('search')) {
            $s = $r->string('search');
            $q->where(fn($qq) => $qq
                ->where('NAME', 'like', "%{$s}%")
                ->orWhere('LAST_NAME', 'like', "%{$s}%")
                ->orWhere('EMPLOYEE_ID', 'like', "%{$s}%")
                ->orWhere('Card', 'like', "%{$s}%")
                ->orWhere('NoIdentitas', 'like', "%{$s}%"));
        }
        if ($r->filled('user_type')) $q->where('user_type', (int) $r->user_type);
        if ($r->filled('is_active')) $q->where('is_active', (int) $r->is_active);
        if ($r->filled('Branchid'))  $q->where('Branchid', (int) $r->Branchid);
        if ($r->filled('Depid'))     $q->where('Depid', (int) $r->Depid);

        $perPage = min(100, max(1, (int) ($r->per_page ?? 20)));
        $withPhoto = $r->boolean('with_photo') || $r->boolean('photo');

        $users = $q->select([
            'ID','EMPLOYEE_ID','NAME','LAST_NAME','BEGIN_DATE','END_DATE','BIRTHDAY',
            'Depid','Branchid','Card','NoIdentitas','user_type','is_active',
            'JOB_POSITION','is_leave','is_freeze','api_last_update','created_at','updated_at',
            DB::raw('photo IS NOT NULL AND LENGTH(photo) > 0 AS has_photo'),
            DB::raw('IFNULL(LENGTH(photo), 0) AS photo_size_bytes'),
        ])->orderByDesc('ID')->paginate($perPage)->withQueryString();

        $users->getCollection()->transform(fn($u) => $this->serialize($u, $withPhoto));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $users,
        ]);
    }

    /* ================= POST /api/usersprofile (CREATE OR UPDATE / UPSERT) ================= */
    public function store(Request $r): JsonResponse
    {
        // 0. Auto-deteksi jika payload adalah bulk (kumpulan banyak user)
        $rawAll = $r->all();
        if (($r->has('users') && is_array($r->input('users'))) || 
            ($r->has('data') && is_array($r->input('data'))) ||
            (is_array($rawAll) && array_is_list($rawAll))) {
            return $this->bulkStore($r);
        }

        // 1. Deteksi apakah user sudah ada (murni berdasarkan EMPLOYEE_ID)
        $employeeId = trim((string) $r->input('EMPLOYEE_ID'));

        $existing = null;
        if (!empty($employeeId)) {
            $existing = UsersProfile::where('EMPLOYEE_ID', $employeeId)->first();
        }

        $existingId = $existing ? $existing->ID : null;

        // 2. Validasi input — jika data sudah ada, ignore ID tersebut agar tidak error duplikat
        $rules = [
            'EMPLOYEE_ID'   => [
                'required', 'string', 'max:15',
                $existingId 
                    ? Rule::unique('usersprofile', 'EMPLOYEE_ID')->ignore($existingId, 'ID')
                    : Rule::unique('usersprofile', 'EMPLOYEE_ID')
            ],
            'NAME'          => ['required', 'string', 'max:100'],
            'LAST_NAME'     => ['nullable', 'string', 'max:50'],
            'BEGIN_DATE'    => ['nullable', 'date'],
            'END_DATE'      => ['nullable', 'date'],
            'BIRTHDAY'      => ['nullable', 'date'],
            'Depid'         => ['nullable', 'integer'],
            'Branchid'      => ['nullable', 'integer'],
            'Card'          => [
                'nullable', 'string', 'max:15', 'different:NoIdentitas',
                Rule::unique('usersprofile', 'Card')
                    ->ignore($existingId, 'ID')
                    ->where(fn($q) => $q->whereNotNull('Card')->where('Card', '!=', ''))
            ],
            'NoIdentitas'   => [
                'nullable', 'string', 'max:30', 'different:Card',
                Rule::unique('usersprofile', 'NoIdentitas')
                    ->ignore($existingId, 'ID')
                    ->where(fn($q) => $q->whereNotNull('NoIdentitas')->where('NoIdentitas', '!=', ''))
            ],
            'user_type'     => ['nullable', 'integer', 'in:0,1,2'],
            'is_active'     => ['nullable', 'integer', 'in:0,1'],
            'JOB_POSITION'  => ['nullable', 'string', 'max:30'],
            'is_leave'      => ['nullable', 'integer', 'in:0,1'],
            'is_freeze'     => ['nullable', 'integer', 'in:0,1'],
        ];

        // Mendukung upload file (jpg, jpeg, webp, png) atau string base64
        if ($r->hasFile('photo')) {
            $rules['photo'] = ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:15360'];
        } else {
            $rules['photo'] = ['nullable', 'string'];
        }

        // Custom error message yang informatif dalam Bahasa Indonesia
        $cardMsg = 'Nomor kartu RFID (:input) sudah digunakan oleh personel lain di sistem.';
        if (!empty($r->input('Card'))) {
            $taken = UsersProfile::where('Card', trim((string)$r->input('Card')))
                ->when($existingId, fn($q) => $q->where('ID', '!=', $existingId))
                ->first();
            if ($taken) {
                $cardMsg = "Nomor kartu RFID '{$taken->Card}' sudah digunakan oleh personel ID {$taken->ID} ({$taken->EMPLOYEE_ID} - {$taken->full_name}). Gunakan nomor kartu lain atau kosongkan jika belum punya kartu fisik.";
            }
        }

        $nikMsg = 'No. Identitas (:input) sudah terdaftar pada personel lain di sistem.';
        if (!empty($r->input('NoIdentitas'))) {
            $takenNik = UsersProfile::where('NoIdentitas', trim((string)$r->input('NoIdentitas')))
                ->when($existingId, fn($q) => $q->where('ID', '!=', $existingId))
                ->where('EMPLOYEE_ID', '!=', $r->input('EMPLOYEE_ID'))
                ->first();
            if ($takenNik) {
                $nikMsg = "No. Identitas (NoIdentitas) '{$takenNik->NoIdentitas}' sudah digunakan oleh personel ID {$takenNik->ID} ({$takenNik->EMPLOYEE_ID} - {$takenNik->full_name}). Gunakan No. Identitas lain.";
            }
        }

        $customMessages = [
            'EMPLOYEE_ID.required' => 'ID Pegawai (EMPLOYEE_ID) wajib diisi.',
            'EMPLOYEE_ID.unique'   => 'ID Pegawai (EMPLOYEE_ID) :input sudah terdaftar pada personel lain.',
            'EMPLOYEE_ID.max'      => 'ID Pegawai (EMPLOYEE_ID) maksimal 15 karakter.',
            'NAME.required'        => 'Nama personel (NAME) wajib diisi.',
            'NAME.max'             => 'Nama personel (NAME) maksimal 100 karakter.',
            'Card.unique'          => $cardMsg,
            'Card.different'       => 'Nomor Kartu (Card) tidak boleh sama dengan No. Identitas (NoIdentitas).',
            'Card.max'             => 'Nomor Kartu (Card) maksimal 15 karakter.',
            'NoIdentitas.unique'   => $nikMsg,
            'NoIdentitas.different'=> 'No. Identitas (NoIdentitas) tidak boleh sama dengan Nomor Kartu (Card).',
            'NoIdentitas.max'      => 'No. Identitas (NoIdentitas) maksimal 30 karakter.',
            'BEGIN_DATE.date'      => 'Format tanggal mulai (BEGIN_DATE) tidak valid. Gunakan format YYYY-MM-DD HH:MM:SS.',
            'END_DATE.date'        => 'Format tanggal berakhir (END_DATE) tidak valid. Gunakan format YYYY-MM-DD HH:MM:SS.',
            'BIRTHDAY.date'        => 'Format tanggal lahir (BIRTHDAY) tidak valid. Gunakan format YYYY-MM-DD.',
            'photo.max'            => 'Ukuran file foto maksimal adalah 15 MB.',
            'photo.mimes'          => 'Format file photo harus berupa JPG, JPEG, PNG, atau WebP.',
        ];

        $data = $r->validate($rules, $customMessages);

        $payload = array_filter($data, fn($v) => !is_null($v), ARRAY_FILTER_USE_KEY);
        $payload['api_last_update'] = now()->format('Y-m-d H:i:s');

        // Proses kompresi photo <= 100 KB
        if ($r->hasFile('photo') || $r->filled('photo')) {
            $processed = $this->processPhotoInput($r);
            if ($processed === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file photo tidak valid atau gambar rusak. Gunakan file JPG, JPEG, PNG, atau WebP.',
                ], 422);
            }
            $payload['photo'] = $processed;
        } else {
            unset($payload['photo']);
        }

        try {
            if ($existing) {
                // UPDATE user yang sudah ada
                $existing->update($payload);

                // Pastikan user terdaftar di seluruh DeviceGate yang ada di usersxdevice
                $this->assignUserToAllDevices($existing->ID);

                $user = $this->baseQuery()->with(['branch:id,name', 'departemen:id,name'])->find($existing->ID);
                return response()->json([
                    'success' => true,
                    'action'  => 'updated',
                    'message' => 'User berhasil diperbarui (updated)',
                    'data'    => $this->serialize($user),
                ], 200);
            } else {
                // CREATE user baru
                $user = UsersProfile::create($payload);

                // Otomatis daftarkan user baru ke seluruh DeviceGate di usersxdevice
                $this->assignUserToAllDevices($user->ID);

                $user = $this->baseQuery()->with(['branch:id,name', 'departemen:id,name'])->find($user->ID);
                return response()->json([
                    'success' => true,
                    'action'  => 'created',
                    'message' => 'User berhasil dibuat (created)',
                    'data'    => $this->serialize($user),
                ], 201);
            }
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses data user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ================= POST /api/usersprofile/bulk (CHUNKED BULK UPSERT) ================= */
    public function bulkStore(Request $r): JsonResponse
    {
        // 1. Ekstrak kumpulan item dari request
        $items = [];
        if ($r->has('users') && is_array($r->input('users'))) {
            $items = $r->input('users');
        } elseif ($r->has('data') && is_array($r->input('data'))) {
            $items = $r->input('data');
        } elseif (is_array($r->all()) && array_is_list($r->all())) {
            $items = $r->all();
        }

        if (empty($items) || !is_array($items)) {
            return response()->json([
                'success' => false,
                'message' => 'Format payload bulk tidak valid. Kirim array JSON atau objek dengan key "users": [...]',
            ], 422);
        }

        $totalReceived = count($items);
        if ($totalReceived > 5000) {
            return response()->json([
                'success' => false,
                'message' => "Maksimal data dalam satu kali bulk request adalah 5.000 record (diterima: {$totalReceived}).",
            ], 422);
        }

        // Antisipasi beban waktu untuk pemrosesan banyak data
        @set_time_limit(300);

        $chunkSize = (int) $r->input('chunk_size', 50);
        if ($chunkSize < 10 || $chunkSize > 200) {
            $chunkSize = 50;
        }

        $chunks = array_chunk($items, $chunkSize);
        $totalCreated = 0;
        $totalUpdated = 0;
        $failedList   = [];
        $startTime    = microtime(true);

        foreach ($chunks as $chunkIndex => $chunk) {
            // Ambil semua EMPLOYEE_ID & Card yang ada di chunk ini
            $chunkEmpIds = [];
            $chunkCards  = [];
            foreach ($chunk as $item) {
                if (!empty($item['EMPLOYEE_ID'])) $chunkEmpIds[] = trim((string)$item['EMPLOYEE_ID']);
                if (!empty($item['Card']))        $chunkCards[]  = trim((string)$item['Card']);
            }

            // Batch fetch existing user dalam chunk ini (hanya 1 query per chunk!)
            $existingByEmp = !empty($chunkEmpIds) 
                ? UsersProfile::whereIn('EMPLOYEE_ID', $chunkEmpIds)->get()->keyBy('EMPLOYEE_ID') 
                : collect();
            $existingByCard = !empty($chunkCards) 
                ? UsersProfile::whereIn('Card', $chunkCards)->get()->keyBy('Card') 
                : collect();

            // Jalankan transaksi per chunk agar aman & hemat memory
            DB::beginTransaction();
            try {
                foreach ($chunk as $i => $item) {
                    $globalIndex = ($chunkIndex * $chunkSize) + $i;
                    $empId = isset($item['EMPLOYEE_ID']) ? trim((string)$item['EMPLOYEE_ID']) : '';
                    $name  = isset($item['NAME']) ? trim((string)$item['NAME']) : '';

                    if (empty($empId) || empty($name)) {
                        $failedList[] = [
                            'index'       => $globalIndex,
                            'EMPLOYEE_ID' => $empId ?: 'KOSONG',
                            'error'       => 'EMPLOYEE_ID dan NAME wajib diisi.',
                        ];
                        continue;
                    }

                    $card = !empty($item['Card']) ? trim((string)$item['Card']) : null;

                    // Cari existing record murni berdasarkan EMPLOYEE_ID
                    $user = $existingByEmp->get($empId);

                    $payload = [
                        'EMPLOYEE_ID'     => $empId,
                        'NAME'            => $name,
                        'LAST_NAME'       => $item['LAST_NAME'] ?? null,
                        'BEGIN_DATE'      => $item['BEGIN_DATE'] ?? null,
                        'END_DATE'        => $item['END_DATE'] ?? null,
                        'BIRTHDAY'        => $item['BIRTHDAY'] ?? null,
                        'Depid'           => isset($item['Depid']) ? (int)$item['Depid'] : null,
                        'Branchid'        => isset($item['Branchid']) ? (int)$item['Branchid'] : null,
                        'Card'            => $card,
                        'NoIdentitas'     => $item['NoIdentitas'] ?? null,
                        'user_type'       => isset($item['user_type']) ? (int)$item['user_type'] : 2,
                        'is_active'       => isset($item['is_active']) ? (int)$item['is_active'] : 1,
                        'JOB_POSITION'    => $item['JOB_POSITION'] ?? null,
                        'is_leave'        => isset($item['is_leave']) ? (int)$item['is_leave'] : 0,
                        'is_freeze'       => isset($item['is_freeze']) ? (int)$item['is_freeze'] : 0,
                        'api_last_update' => now()->format('Y-m-d H:i:s'),
                    ];

                    $payload = array_filter($payload, fn($v) => !is_null($v), ARRAY_FILTER_USE_KEY);

                    // Proses foto jika ada (format base64)
                    if (!empty($item['photo']) && is_string($item['photo'])) {
                        $photoRaw = $this->decodeBase64Photo($item['photo']);
                        if ($photoRaw !== null) {
                            $optimized = $this->optimizePhotoRaw($photoRaw);
                            if ($optimized !== false) {
                                $payload['photo'] = $optimized;
                            }
                        }
                    }

                    if ($user) {
                        // UPDATE
                        $user->update($payload);
                        $this->assignUserToAllDevices($user->ID);
                        $totalUpdated++;
                    } else {
                        // CREATE
                        $created = UsersProfile::create($payload);
                        $existingByEmp->put($empId, $created);
                        if ($card) $existingByCard->put($card, $created);
                        $this->assignUserToAllDevices($created->ID);
                        $totalCreated++;
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $failedList[] = [
                    'chunk' => $chunkIndex + 1,
                    'error' => 'Gagal memproses batch: ' . $e->getMessage(),
                ];
            }

            // Bersihkan variabel dan panggil Garbage Collector agar memory selalu flat
            unset($existingByEmp, $existingByCard, $chunkEmpIds, $chunkCards);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $duration = round(microtime(true) - $startTime, 3);

        return response()->json([
            'success' => count($failedList) === 0,
            'message' => "Proses bulk selesai ({$totalCreated} dibuat, {$totalUpdated} diperbarui, " . count($failedList) . " gagal)",
            'summary' => [
                'total_received'         => $totalReceived,
                'created'                => $totalCreated,
                'updated'                => $totalUpdated,
                'failed'                 => count($failedList),
                'chunk_size'             => $chunkSize,
                'total_chunks'           => count($chunks),
                'execution_time_seconds' => $duration,
            ],
            'errors' => $failedList,
        ], count($failedList) === 0 ? 200 : 207);
    }

    /* ================= GET /api/usersprofile/{id} ================= */
    public function show(int $id): JsonResponse
    {
        $user = $this->baseQuery()->with(['devices.gate:id,name,sn,ip,stat'])->find($id);
        if (!$user) {
            return response()->json(['success'=>false,'message'=>'User tidak ditemukan'], 404);
        }
        return response()->json(['success'=>true,'message'=>'OK','data'=>$this->serialize($user, true)]);
    }

    /* ================= PUT /api/usersprofile/{id} ================= */
    public function update(Request $r, int $id): JsonResponse
    {
        $user = UsersProfile::find($id);
        if (!$user) {
            return response()->json(['success'=>false,'message'=>'User tidak ditemukan'], 404);
        }

        $rules = [
            'EMPLOYEE_ID'   => ['sometimes','required','string','max:15', Rule::unique('usersprofile','EMPLOYEE_ID')->ignore($id,'ID')],
            'NAME'          => ['sometimes','required','string','max:100'],
            'LAST_NAME'     => ['nullable','string','max:50'],
            'BEGIN_DATE'    => ['nullable','date'],
            'END_DATE'      => ['nullable','date'],
            'BIRTHDAY'      => ['nullable','date'],
            'Depid'         => ['nullable','integer'],
            'Branchid'      => ['nullable','integer'],
            'Card'          => ['nullable','string','max:15', Rule::unique('usersprofile','Card')->ignore($id,'ID')->where(fn($q) => $q->whereNotNull('Card'))],
            'NoIdentitas'   => ['nullable','string','max:30'],
            'user_type'     => ['nullable','integer','in:0,1,2'],
            'is_active'     => ['nullable','integer','in:0,1'],
            'JOB_POSITION'  => ['nullable','string','max:30'],
            'is_leave'      => ['nullable','integer','in:0,1'],
            'is_freeze'     => ['nullable','integer','in:0,1'],
            'remove_photo'  => ['nullable','boolean'],
        ];

        if ($r->hasFile('photo')) {
            $rules['photo'] = ['nullable', 'file', 'mimes:jpeg,jpg,png,webp', 'max:15360'];
        } else {
            $rules['photo'] = ['nullable', 'string'];
        }

        $data = $r->validate($rules);

        $payload = array_filter($data, fn($v) => !is_null($v) && $v !== '', ARRAY_FILTER_USE_KEY);
        $payload['api_last_update'] = now()->format('Y-m-d H:i:s');

        if ($r->boolean('remove_photo')) {
            $payload['photo'] = null;
            unset($payload['remove_photo']);
        } elseif ($r->hasFile('photo') || $r->filled('photo')) {
            $processed = $this->processPhotoInput($r);
            if ($processed === false) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file photo tidak valid atau gambar rusak. Gunakan file JPG, JPEG, PNG, atau WebP.',
                ], 422);
            }
            $payload['photo'] = $processed;
        }

        try {
            $user->update($payload);
            $user = $this->baseQuery()->with(['branch:id,name','departemen:id,name'])->find($id);
            return response()->json([
                'success' => true,
                'message' => 'User berhasil diperbarui',
                'data' => $this->serialize($user),
            ]);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /* ================= DELETE /api/usersprofile/bulk (CHUNKED BULK DELETE) ================= */
    public function bulkDestroy(Request $r): JsonResponse
    {
        // 1. Ekstrak target-target yang ingin dihapus
        $targets = [];

        if ($r->has('employee_ids') && is_array($r->input('employee_ids'))) {
            $targets = $r->input('employee_ids');
        } elseif ($r->has('ids') && is_array($r->input('ids'))) {
            $targets = $r->input('ids');
        } elseif ($r->has('cards') && is_array($r->input('cards'))) {
            $targets = $r->input('cards');
        } elseif ($r->has('users') && is_array($r->input('users'))) {
            foreach ($r->input('users') as $u) {
                if (is_array($u)) {
                    $val = $u['EMPLOYEE_ID'] ?? $u['id'] ?? $u['ID'] ?? $u['Card'] ?? null;
                    if ($val) $targets[] = $val;
                } elseif (is_scalar($u)) {
                    $targets[] = $u;
                }
            }
        } elseif (is_array($r->all()) && array_is_list($r->all())) {
            foreach ($r->all() as $item) {
                if (is_array($item)) {
                    $val = $item['EMPLOYEE_ID'] ?? $item['id'] ?? $item['ID'] ?? $item['Card'] ?? null;
                    if ($val) $targets[] = $val;
                } elseif (is_scalar($item)) {
                    $targets[] = $item;
                }
            }
        }

        // Bersihkan dan deduplikasi target
        $targets = array_values(array_unique(array_filter(array_map('trim', $targets), fn($v) => $v !== '')));

        if (empty($targets)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada target yang dikirim. Kirim array "employee_ids": ["EMP-001", ...] atau "ids": [1, 2, ...]',
            ], 422);
        }

        $totalRequested = count($targets);
        if ($totalRequested > 10000) {
            return response()->json([
                'success' => false,
                'message' => "Maksimal data dalam satu kali bulk delete adalah 10.000 record (diterima: {$totalRequested}).",
            ], 422);
        }

        @set_time_limit(300);

        $chunkSize = (int) $r->input('chunk_size', 100);
        if ($chunkSize < 10 || $chunkSize > 500) {
            $chunkSize = 100;
        }

        $chunks = array_chunk($targets, $chunkSize);
        $totalDeleted = 0;
        $deletedRecords = [];
        $startTime = microtime(true);

        foreach ($chunks as $chunk) {
            $numericIds = array_values(array_filter($chunk, 'is_numeric'));

            $usersToDelete = UsersProfile::select(['ID', 'EMPLOYEE_ID', 'NAME', 'Card'])
                ->where(function ($q) use ($chunk, $numericIds) {
                    $q->whereIn('EMPLOYEE_ID', $chunk);
                    if (!empty($numericIds)) {
                        $q->orWhereIn('ID', $numericIds);
                    }
                    $q->orWhereIn('Card', $chunk);
                })
                ->get();

            if ($usersToDelete->isNotEmpty()) {
                $ids = $usersToDelete->pluck('ID')->all();

                DB::beginTransaction();
                try {
                    // Hapus relasi gate device
                    UsersXDevice::whereIn('userId', $ids)->delete();
                    // Hapus user secara bulk dalam 1 query!
                    $count = UsersProfile::whereIn('ID', $ids)->delete();
                    DB::commit();

                    $totalDeleted += $count;
                    if ($totalRequested <= 50) {
                        foreach ($usersToDelete as $u) {
                            $deletedRecords[] = [
                                'ID'          => $u->ID,
                                'EMPLOYEE_ID' => $u->EMPLOYEE_ID,
                                'NAME'        => $u->NAME,
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    DB::rollBack();
                    report($e);
                }
            }

            unset($usersToDelete, $numericIds, $ids);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $duration = round(microtime(true) - $startTime, 3);
        $notFound = max(0, $totalRequested - $totalDeleted);

        return response()->json([
            'success' => true,
            'message' => "Bulk delete selesai ({$totalDeleted} user dihapus dari {$totalRequested} target dalam " . count($chunks) . " chunk)",
            'summary' => [
                'total_requested'        => $totalRequested,
                'deleted'                => $totalDeleted,
                'not_found'              => $notFound,
                'chunk_size'             => $chunkSize,
                'total_chunks'           => count($chunks),
                'execution_time_seconds' => $duration,
            ],
            'deleted_items' => $deletedRecords,
        ], 200);
    }

    /* ================= DELETE /api/usersprofile/{id?} ================= */
    public function destroy(Request $r, ?string $id = null): JsonResponse
    {
        // 0. Auto-deteksi jika request adalah bulk delete
        $rawAll = $r->all();
        if (empty($id) && (
            $r->has('employee_ids') || 
            $r->has('ids') || 
            $r->has('cards') || 
            $r->has('users') || 
            (is_array($rawAll) && array_is_list($rawAll))
        )) {
            return $this->bulkDestroy($r);
        }

        $target = $id ?? $r->input('EMPLOYEE_ID') ?? $r->input('id') ?? $r->input('Card');

        if (empty($target)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter ID atau EMPLOYEE_ID wajib diisi (di URL atau di Body JSON)',
            ], 422);
        }

        $user = null;
        if (is_numeric($target)) {
            $user = UsersProfile::find((int) $target);
        }
        if (!$user) {
            $user = UsersProfile::where('EMPLOYEE_ID', $target)->first();
        }
        if (!$user) {
            $user = UsersProfile::where('Card', $target)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => "User dengan ID / EMPLOYEE_ID / Kartu '{$target}' tidak ditemukan",
            ], 404);
        }

        try {
            $deletedData = [
                'ID'          => $user->ID,
                'EMPLOYEE_ID' => $user->EMPLOYEE_ID,
                'NAME'        => $user->NAME,
                'Card'        => $user->Card,
            ];
            // Hapus relasi gate device
            UsersXDevice::where('userId', $user->ID)->delete();
            // Hapus user
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => "User '{$deletedData['NAME']}' ({$deletedData['EMPLOYEE_ID']}) berhasil dihapus",
                'deleted' => $deletedData,
            ], 200);
        } catch (\Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /* ================= GET /api/usersprofile/{id}/photo ================= */
    public function photo(int $id)
    {
        $user = UsersProfile::select(['ID','photo'])->find($id);
        if (!$user || empty($user->photo)) {
            return response()->json(['success' => false, 'message' => 'Foto tidak ditemukan'], 404);
        }

        $raw  = $user->photo;
        $mime = 'image/jpeg';
        $bin  = null;

        // 1. Format Data URI
        if (is_string($raw) && str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            $bin = isset($parts[1]) ? base64_decode($parts[1], true) : null;
            if ($bin !== null) {
                if (str_contains($parts[0], 'webp')) $mime = 'image/webp';
                elseif (str_contains($parts[0], 'png'))  $mime = 'image/png';
            }
        }

        // 2. Format Base64 murni
        if ($bin === null) {
            $decoded = is_string($raw) ? base64_decode($raw, true) : false;
            if ($decoded !== false && base64_encode($decoded) === trim($raw)) {
                $bin = $decoded;
            }
        }

        // 3. Format Binary murni
        if ($bin === null) {
            $bin = $raw;
        }

        // Deteksi MIME type dari magic bytes
        if (str_starts_with($bin, "RIFF")) {
            $mime = 'image/webp';
        } elseif (str_starts_with($bin, "\x89PNG")) {
            $mime = 'image/png';
        } elseif (str_starts_with($bin, "\xFF\xD8")) {
            $mime = 'image/jpeg';
        }

        return response($bin, 200, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="avatar_' . $id . '.jpg"',
            'Cache-Control'       => 'public, max-age=86400',
        ]);
    }

    /* ================= POST /api/usersprofile/{id}/sync-device ================= */
    public function syncToDevice(Request $r, int $id): JsonResponse
    {
        $user = UsersProfile::find($id);
        if (!$user) {
            return response()->json(['success'=>false,'message'=>'User tidak ditemukan'], 404);
        }

        $data = $r->validate([
            'gate_id' => ['required','integer','exists:devicegate,id'],
        ]);

        $gate = \App\Models\DeviceGate::find($data['gate_id']);
        $ep2  = \App\Models\ApiEndpoint::find(2); // registerface
        $ep3  = \App\Models\ApiEndpoint::find(3); // useraccess

        if (!$ep2 || !$ep3) {
            return response()->json(['success'=>false,'message'=>'ApiEndpoint 2 atau 3 belum ada (seed dulu)'], 500);
        }

        $fdata = $this->extractFdata($user);
        $results = [];

        // 1. registerface
        $payload = [
            'sn'         => (string) $gate->sn,
            'userid'     => (string) $user->ID,
            'username'   => (string) $user->NAME,
            'type'       => '50',
            'admin'      => '0',
            'cardnumber' => (string) ($user->Card ?? '0'),
            'timezone'   => '0',
            'fdata'      => $fdata,
        ];
        try {
            $res = Http::withoutVerifying()->timeout(12)->post($ep2->url, $payload);
            $results['registerface'] = [
                'ok' => $res->successful(),
                'status' => $res->status(),
                'body' => $this->safeJson($res->body()),
            ];
        } catch (\Throwable $e) {
            $results['registerface'] = ['ok'=>false,'error'=>$e->getMessage()];
        }

        // 2. useraccess (zona waktu akses)
        $starttime = $user->BEGIN_DATE ? $user->BEGIN_DATE->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s');
        $endtime   = $user->END_DATE   ? $user->END_DATE->format('Y-m-d H:i:s')   : '2079-12-31 23:59:59';
        try {
            $res2 = Http::withoutVerifying()->timeout(12)->post($ep3->url, [[
                'sn'         => (string) $gate->sn,
                'userid'     => (string) $user->ID,
                'starttime'  => $starttime,
                'endtime'    => $endtime,
                'weekzone'   => 0,
            ]]);
            $results['useraccess'] = [
                'ok' => $res2->successful(),
                'status' => $res2->status(),
                'body' => $this->safeJson($res2->body()),
            ];
        } catch (\Throwable $e) {
            $results['useraccess'] = ['ok'=>false,'error'=>$e->getMessage()];
        }

        // 3. catet relasi user <-> gate
        try {
            UsersXDevice::firstOrCreate(
                ['userId'=>$user->ID,'gateId'=>$gate->id],
                ['Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0]
            );
        } catch (\Throwable $e) {}

        $allOk = collect($results)->every(fn($v) => ($v['ok'] ?? false) === true);
        return response()->json([
            'success' => $allOk,
            'message' => $allOk ? 'Sync ke device berhasil' : 'Sinkronisasi selesai (sebagian gagal)',
            'device'  => ['id'=>$gate->id,'name'=>$gate->name,'sn'=>$gate->sn],
            'results' => $results,
        ], $allOk ? 200 : 207);
    }

    /* ================= HELPERS ================= */
    /**
     * Otomatis membuat record relasi UsersXDevice untuk user ke semua DeviceGate
     */
    protected function assignUserToAllDevices(int $userId): void
    {
        try {
            $gateIds = \App\Models\DeviceGate::pluck('id');
            foreach ($gateIds as $gateId) {
                UsersXDevice::firstOrCreate(
                    ['userId' => $userId, 'gateId' => $gateId],
                    ['Lift1' => 0, 'Lift2' => 0, 'Lift3' => 0, 'Lift4' => 0]
                );
            }
        } catch (\Throwable $e) {
            // silent fail agar tidak memblokir response
        }
    }

    protected function baseQuery()
    {
        return UsersProfile::select([
            'ID','EMPLOYEE_ID','NAME','LAST_NAME','BEGIN_DATE','END_DATE','BIRTHDAY',
            'Depid','Branchid','Card','NoIdentitas','user_type','is_active',
            'JOB_POSITION','is_leave','is_freeze','api_last_update','created_at','updated_at',
            DB::raw('photo IS NOT NULL AND LENGTH(photo) > 0 AS has_photo'),
            DB::raw('IFNULL(LENGTH(photo), 0) AS photo_size_bytes'),
        ])->with(['branch:id,name', 'departemen:id,name']);
    }

    protected function serialize($user, ?bool $includePhoto = null): array
    {
        if (!$user) return [];
        $arr = $user->toArray();

        // 1. Format Depid, Branchid, dan user_type sesuai relasi model & label
        $depName = $user->departemen?->name ?? ($arr['departemen']['name'] ?? null);
        $branchName = $user->branch?->name ?? ($arr['branch']['name'] ?? null);
        unset($arr['branch'], $arr['departemen']);

        $userTypeId = isset($arr['user_type']) ? (int) $arr['user_type'] : null;
        $userTypeLabel = match ($userTypeId) {
            1 => 'Member',
            2 => 'Karyawan',
            default => 'Unknown'
        };

        // Tampilkan nama model & label sesuai permintaan (bersih tanpa ID tambahan)
        $arr['Depid']     = $depName ?? $arr['Depid'];
        $arr['Branchid']  = $branchName ?? $arr['Branchid'];
        $arr['user_type'] = $userTypeLabel;

        $bytes = (int) ($arr['photo_size_bytes'] ?? 0);
        $arr['photo_size_kb'] = $bytes > 0 ? round($bytes / 1024, 2) : 0;
        $arr['photo_url'] = !empty($arr['has_photo']) ? url("/api/usersprofile/{$arr['ID']}/photo") : null;

        // Cek apakah foto dalam format Base64 perlu disertakan
        $shouldInclude = $includePhoto ?? (request()->boolean('with_photo') || request()->boolean('photo'));
        if ($shouldInclude && !empty($arr['has_photo'])) {
            $raw = $user->photo ?? UsersProfile::where('ID', $arr['ID'])->value('photo');
            if (!empty($raw)) {
                $rawB64 = '';
                $mime = 'image/jpeg';
                if (is_string($raw) && str_starts_with($raw, 'data:')) {
                    $parts = explode(',', $raw, 2);
                    $rawB64 = $parts[1] ?? '';
                    if (str_contains($parts[0], 'webp')) $mime = 'image/webp';
                    elseif (str_contains($parts[0], 'png')) $mime = 'image/png';
                } elseif (is_string($raw) && base64_decode($raw, true) !== false && base64_encode(base64_decode($raw, true)) === trim($raw)) {
                    $rawB64 = trim($raw);
                } else {
                    if (str_starts_with($raw, "RIFF")) $mime = 'image/webp';
                    elseif (str_starts_with($raw, "\x89PNG")) $mime = 'image/png';
                    $rawB64 = base64_encode($raw);
                }
                $arr['photo'] = 'data:' . $mime . ';base64,' . $rawB64;
                $arr['photo_base64'] = $rawB64;
            } else {
                $arr['photo'] = null;
                $arr['photo_base64'] = null;
            }
        } else {
            unset($arr['photo']);
        }

        return $arr;
    }

    /**
     * Decode string base64 / Data URI menjadi binary image
     */
    protected function decodeBase64Photo(?string $raw): ?string
    {
        if (empty($raw) || !is_string($raw)) return null;

        if (str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            return isset($parts[1]) ? base64_decode($parts[1], true) : null;
        }

        $decoded = base64_decode($raw, true);
        if ($decoded !== false && base64_encode($decoded) === trim($raw)) {
            return $decoded;
        }

        if (str_starts_with($raw, "RIFF") || str_starts_with($raw, "\x89PNG") || str_starts_with($raw, "\xFF\xD8")) {
            return $raw;
        }

        return null;
    }

    /**
     * Memproses input foto dari file upload (multipart/form-data)
     * ataupun string base64 / data URI.
     */
    protected function processPhotoInput(Request $r): string|false|null
    {
        $bin = null;

        // 1. Dari multipart file upload
        if ($r->hasFile('photo')) {
            $file = $r->file('photo');
            if (!$file->isValid()) return false;
            $bin = @file_get_contents($file->getPathname());
        } elseif ($r->filled('photo')) {
            // 2. Dari base64 string / data URI
            $bin = $this->decodeBase64Photo($r->input('photo'));
        }

        if (empty($bin)) return null;

        return $this->optimizePhotoRaw($bin);
    }

    /**
     * Mengoptimasi raw binary foto ke JPEG berkualitas tinggi dalam rentang [20 KB - 100 KB]
     * (Standar emas kamera pengenal wajah turnstile gate).
     */
    protected function optimizePhotoRaw(string $bin): string|false
    {
        // Jika GD belum terpasang/aktif, kembalikan binary asli agar tidak fatal error
        if (!function_exists('imagecreatefromstring')) {
            return $bin;
        }

        // Baca gambar dengan GD
        $src = @imagecreatefromstring($bin);
        if (!$src) return $bin;

        $minBytes = 20 * 1024;  // Minimal 20 KB (20.480 bytes)
        $maxBytes = 100 * 1024; // Maksimal 100 KB (102.400 bytes)

        $w = imagesx($src);
        $h = imagesy($src);

        // 1. Jika resolusi foto terlalu kecil (< 600px), naikkan ke dimensi standar pengenal wajah
        $targetMinSide = 600;
        if (max($w, $h) < $targetMinSide) {
            $ratio = $targetMinSide / max($w, $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
            $w = $nw;
            $h = $nh;
        }

        // 2. Jika resolusi terlalu besar (> 1000px), kecilkan agar pas dengan buffer mesin
        $targetMaxSide = 1000;
        if (max($w, $h) > $targetMaxSide) {
            $ratio = $targetMaxSide / max($w, $h);
            $nw = (int) round($w * $ratio);
            $nh = (int) round($h * $ratio);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($src);
            $src = $dst;
            $w = $nw;
            $h = $nh;
        }

        // 3. Kompresi JPEG berkualitas tinggi bertingkat mencari rentang 20KB - 100KB
        $bestJpeg = null;
        $qualities = [96, 94, 92, 90, 85, 80, 75, 70];
        foreach ($qualities as $q) {
            ob_start();
            imagejpeg($src, null, $q);
            $candidate = ob_get_clean();
            $sz = strlen($candidate);
            if ($sz <= $maxBytes) {
                $bestJpeg = $candidate;
                if ($sz >= $minBytes) {
                    break; // Sempurna: sudah berada di rentang 20KB - 100KB
                }
            }
        }

        // 4. Jika foto terlalu sederhana sehingga masih < 20KB, scale up 35% lagi
        if ($bestJpeg && strlen($bestJpeg) < $minBytes && max($w, $h) < 1400) {
            $scale = 1.35;
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 94);
            $upCandidate = ob_get_clean();
            imagedestroy($dst);
            if (strlen($upCandidate) <= $maxBytes) {
                $bestJpeg = $upCandidate;
            }
        }

        // 5. Jaminan fisik minimal 20 KB menggunakan safe JPEG comment marker (COM)
        if ($bestJpeg && strlen($bestJpeg) < $minBytes) {
            $padNeeded = $minBytes - strlen($bestJpeg) + 256;
            if (strlen($bestJpeg) > 4 && substr($bestJpeg, 0, 2) === "\xFF\xD8") {
                $padLen = min($padNeeded, 65530);
                $comment = str_repeat("0", $padLen);
                $markerLen = $padLen + 2;
                $marker = "\xFF\xFE" . chr(($markerLen >> 8) & 0xFF) . chr($markerLen & 0xFF) . $comment;
                $bestJpeg = substr($bestJpeg, 0, 2) . $marker . substr($bestJpeg, 2);
            }
        }

        imagedestroy($src);

        if (!$bestJpeg || strlen($bestJpeg) > $maxBytes) {
            return false;
        }

        return $bestJpeg;
    }

    protected function extractFdata($user): string
    {
        if (!$user->photo) return '';
        $raw = $user->photo;
        if (is_string($raw) && str_starts_with($raw, 'data:')) {
            $parts = explode(',', $raw, 2);
            return $parts[1] ?? '';
        }
        $decoded = base64_decode($raw, true);
        if ($decoded !== false && base64_encode($decoded) === trim($raw)) {
            return trim($raw);
        }
        // binary murni → encode ke base64 untuk dikirim via JSON
        return base64_encode($raw);
    }

    protected function safeJson(string $raw): mixed
    {
        $d = json_decode($raw, true);
        return json_last_error() === JSON_ERROR_NONE ? $d : $raw;
    }
}
