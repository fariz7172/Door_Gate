<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TblUserlog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserlogApiController extends Controller
{
    /**
     * GET /api/userlog
     * List riwayat tap/scan turnstile dengan filter lengkap & pagination
     */
    public function index(Request $r): JsonResponse
    {
        $q = TblUserlog::query()->with([
            'user.departemen',
            'user.branch',
            'device:id,name,sn,ip,stat'
        ]);

        // Filter: Search umum (nama, NIK, kartu, SN)
        if ($r->filled('search')) {
            $s = $r->string('search');
            $q->where(function ($qq) use ($s) {
                $qq->where('DEVICESN', 'like', "%{$s}%")
                   ->orWhere('card', 'like', "%{$s}%")
                   ->orWhereHas('user', function ($uq) use ($s) {
                       $uq->where('NAME', 'like', "%{$s}%")
                          ->orWhere('EMPLOYEE_ID', 'like', "%{$s}%")
                          ->orWhere('Card', 'like', "%{$s}%");
                   });
            });
        }

        // Filter: ID User (usersprofile.ID)
        if ($r->filled('user_id')) {
            $q->where('USER_ADDR', (int) $r->user_id);
        } elseif ($r->filled('USER_ADDR')) {
            $q->where('USER_ADDR', (int) $r->USER_ADDR);
        }

        // Filter: NIK / EMPLOYEE_ID
        if ($r->filled('employee_id')) {
            $emp = $r->string('employee_id');
            $q->whereHas('user', fn($uq) => $uq->where('EMPLOYEE_ID', $emp));
        }

        // Filter: SN Mesin Gate
        if ($r->filled('devicesn')) {
            $q->where('DEVICESN', $r->string('devicesn'));
        } elseif ($r->filled('device_sn')) {
            $q->where('DEVICESN', $r->string('device_sn'));
        }

        // Filter: Status IN / OUT (0 = IN, 1 = OUT)
        if ($r->filled('stat')) {
            $q->where('stat', (int) $r->stat);
        }

        // Filter: Tanggal spesifik (YYYY-MM-DD)
        if ($r->filled('date')) {
            $d = $r->string('date');
            $q->whereBetween('TM_EVENT', ["{$d} 00:00:00", "{$d} 23:59:59"]);
        }

        // Filter: Rentang tanggal (start_date s/d end_date)
        if ($r->filled('start_date')) {
            $q->where('TM_EVENT', '>=', $r->string('start_date') . ' 00:00:00');
        }
        if ($r->filled('end_date')) {
            $q->where('TM_EVENT', '<=', $r->string('end_date') . ' 23:59:59');
        }

        // Filter: Scan Method ('face', 'card', 'qr', dll)
        if ($r->filled('scan_method')) {
            $q->where('scan_method', $r->string('scan_method'));
        }

        $perPage = min(100, max(1, (int) ($r->per_page ?? 20)));

        $logs = $q->orderByDesc('REC_INDEX')
                  ->paginate($perPage)
                  ->withQueryString();

        $logs->getCollection()->transform(fn($log) => $this->serialize($log));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => $logs,
        ]);
    }

    /**
     * GET /api/userlog/latest
     * Ambil 10 log tap gerbang paling baru (real-time monitoring)
     */
    public function latest(Request $r): JsonResponse
    {
        $limit = min(50, max(1, (int) ($r->limit ?? 10)));

        $logs = TblUserlog::with([
            'user.departemen',
            'user.branch',
            'device:id,name,sn,ip,stat'
        ])
        ->orderByDesc('REC_INDEX')
        ->limit($limit)
        ->get()
        ->map(fn($log) => $this->serialize($log));

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => $logs,
        ]);
    }

    /**
     * GET /api/userlog/{id}
     * Ambil detail 1 baris log kejadian tap gerbang berdasarkan REC_INDEX
     */
    public function show(int $id): JsonResponse
    {
        $log = TblUserlog::with([
            'user.departemen',
            'user.branch',
            'device:id,name,sn,ip,stat'
        ])->find($id);

        if (!$log) {
            return response()->json([
                'success' => false,
                'message' => 'Log aktivitas tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data'    => $this->serialize($log),
        ]);
    }

    /**
     * Serialize model log ke array JSON bersih dan terstruktur
     */
    protected function serialize(TblUserlog $log): array
    {
        $user = $log->user;
        $device = $log->device;

        $userTypeLabel = match ((int) ($user?->user_type ?? 0)) {
            1 => 'Member',
            2 => 'Karyawan',
            default => 'Unknown'
        };

        return [
            'REC_INDEX'     => $log->REC_INDEX,
            'USER_ADDR'     => $log->USER_ADDR,
            'employee_id'   => $user?->EMPLOYEE_ID,
            'name'          => $user?->NAME ?? ('User #' . $log->USER_ADDR),
            'last_name'     => $user?->LAST_NAME,
            'department'    => $user?->departemen?->name,
            'branch'        => $user?->branch?->name,
            'user_type'     => $userTypeLabel,
            'TM_EVENT'      => $log->TM_EVENT ? $log->TM_EVENT->format('Y-m-d H:i:s') : null,
            'DEVICESN'      => $log->DEVICESN,
            'device_name'   => $device?->name ?? 'Turnstile Gate',
            'device_ip'     => $device?->ip,
            'card'          => $log->card ?: ($user?->Card ?? null),
            'stat'          => (int) $log->stat,
            'stat_label'    => (int) $log->stat === 1 ? 'OUT' : 'IN',
            'scan_method'   => $log->scan_method ?: 'card',
            'photo_url'     => $log->USER_ADDR ? url("/api/usersprofile/{$log->USER_ADDR}/photo") : null,
        ];
    }
}
