<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TblUserlog;
use Illuminate\Support\Facades\DB;

class GateApilogController extends Controller
{
    /**
     * Halaman Log Gate Hardware (tbl_userlog)
     */
    public function index(Request $r)
    {
        $startDate = $r->input('start_date');
        $endDate = $r->input('end_date');

        if (!$r->has('start_date') && !$r->has('end_date')) {
            $startDate = now()->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        }

        $search = $r->input('q');
        $statFilter = $r->input('stat');

        $qUser = TblUserlog::with(['user', 'device'])->orderByDesc('REC_INDEX');

        if (!empty($startDate) && !empty($endDate)) {
            $qUser->whereBetween('TM_EVENT', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        } elseif (!empty($startDate)) {
            $qUser->where('TM_EVENT', '>=', $startDate . ' 00:00:00');
        } elseif (!empty($endDate)) {
            $qUser->where('TM_EVENT', '<=', $endDate . ' 23:59:59');
        }

        if (!empty($search)) {
            $qUser->where(function($w) use ($search){
                $w->where('DEVICESN', 'like', "%{$search}%")
                  ->orWhere('card', 'like', "%{$search}%")
                  ->orWhere('USER_ADDR', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search){
                      $uq->where('NAME', 'like', "%{$search}%");
                  });
            });
        }

        if ($statFilter !== null && in_array($statFilter, ['0','1'], true)) {
            $qUser->where('stat', (int) $statFilter);
        }

        $userStats = (clone $qUser)->selectRaw("COUNT(*) as total, SUM(CASE WHEN stat=0 THEN 1 ELSE 0 END) as in_cnt, SUM(CASE WHEN stat=1 THEN 1 ELSE 0 END) as out_cnt")->first();
        $userTotal = (int) ($userStats->total ?? 0);
        $userIn = (int) ($userStats->in_cnt ?? 0);
        $userOut = (int) ($userStats->out_cnt ?? 0);

        $perPage = 25;
        $logs = $qUser->paginate($perPage)->withQueryString();

        $logs->getCollection()->transform(function($ul){
            $m = strtolower((string)($ul->scan_method ?: 'card'));
            $mLabel = $m === 'face' ? 'Face' : ($m === 'finger' ? 'Finger' : 'RFID Card');
            return (object) [
                'type' => 'userlog',
                'raw_id' => $ul->REC_INDEX,
                'uid' => 'user_' . $ul->REC_INDEX,
                'time' => $ul->TM_EVENT,
                'sn' => $ul->DEVICESN,
                'device_name' => $ul->device?->name ?: 'Turnstile Gate',
                'user_id' => $ul->USER_ADDR,
                'user_name' => $ul->user?->NAME ?? ('User #' . $ul->USER_ADDR),
                'card_no' => $ul->card ?: ($ul->user?->Card ?? ''),
                'stat' => (int) $ul->stat,
                'stat_label' => (int)$ul->stat === 1 ? 'OUT' : 'IN',
                'method' => $m,
                'method_label' => $mLabel,
                'access' => 1,
                'is_granted' => true,
                'detail' => 'Tap Mesin (' . $mLabel . ')',
                'status_code' => 200,
                'img' => $ul->IMGPATH,
            ];
        });

        $lastUserlogId = TblUserlog::max('REC_INDEX') ?? 0;

        return view('gate_logs.index', compact(
            'logs', 'startDate', 'endDate',
            'userTotal', 'userIn', 'userOut',
            'lastUserlogId'
        ));
    }

    /**
     * Live Feed Polling untuk Hardware tap (tbl_userlog)
     */
    public function feed(Request $r)
    {
        $afterUserId = (int) $r->query('after_user_id', 0);
        $today = now()->format('Y-m-d');

        $newUser = TblUserlog::with(['user', 'device'])
            ->where('REC_INDEX', '>', $afterUserId)
            ->whereBetween('TM_EVENT', [$today . ' 00:00:00', $today . ' 23:59:59'])
            ->orderBy('REC_INDEX', 'asc')
            ->limit(20)
            ->get();

        $newItems = collect();

        foreach ($newUser as $ul) {
            $m = strtolower((string)($ul->scan_method ?: 'card'));
            $mLabel = $m === 'face' ? 'Face' : ($m === 'finger' ? 'Finger' : 'RFID Card');
            $newItems->push([
                'type' => 'userlog',
                'id' => $ul->REC_INDEX,
                'uid' => 'user_' . $ul->REC_INDEX,
                'time' => $ul->TM_EVENT?->format('d/m/Y H:i:s'),
                'sn' => $ul->DEVICESN,
                'device_name' => $ul->device?->name ?: 'Turnstile Gate',
                'user_name' => $ul->user?->NAME ?? ('User #' . $ul->USER_ADDR),
                'user_id' => $ul->USER_ADDR,
                'card_no' => $ul->card ?: ($ul->user?->Card ?? '—'),
                'stat_label' => (int)$ul->stat === 1 ? 'OUT' : 'IN',
                'stat' => (int) $ul->stat,
                'method' => $m,
                'method_label' => $mLabel,
                'is_granted' => true,
                'access' => 1,
                'status_code' => 200,
                'detail' => 'Tap Mesin (' . $mLabel . ')',
                'img' => $ul->IMGPATH,
                'raw_timestamp' => $ul->TM_EVENT?->timestamp ?? 0,
            ]);
        }

        $sortedNew = $newItems->sortBy('raw_timestamp')->values();

        // Hitung statistik hari ini secara realtime untuk update kartu atas
        $statsToday = TblUserlog::whereBetween('TM_EVENT', [$today . ' 00:00:00', $today . ' 23:59:59'])
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN stat=0 THEN 1 ELSE 0 END) as in_cnt, SUM(CASE WHEN stat=1 THEN 1 ELSE 0 END) as out_cnt")
            ->first();

        return response()->json([
            'logs' => $sortedNew,
            'count' => $sortedNew->count(),
            'last_user_id' => $newUser->max('REC_INDEX') ?? $afterUserId,
            'stats' => [
                'total' => (int) ($statsToday->total ?? 0),
                'in'    => (int) ($statsToday->in_cnt ?? 0),
                'out'   => (int) ($statsToday->out_cnt ?? 0),
            ],
        ]);
    }

    /**
     * Export Excel tbl_userlog
     */
    public function exportExcel(Request $r)
    {
        $startDate = $r->input('start_date');
        $endDate = $r->input('end_date');
        if (!$r->has('start_date') && !$r->has('end_date')) {
            $startDate = now()->format('Y-m-d');
            $endDate = now()->format('Y-m-d');
        }

        $search = $r->input('q');
        $statFilter = $r->input('stat');

        $titlePeriode = ($startDate && $endDate) ? "Periode: {$startDate} s/d {$endDate}" : "Semua Data";

        $qUser = TblUserlog::with(['user', 'device'])->orderByDesc('REC_INDEX');
        if (!empty($startDate) && !empty($endDate)) {
            $qUser->whereBetween('TM_EVENT', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }
        if (!empty($search)) {
            $qUser->where(function($w) use ($search){
                $w->where('DEVICESN','like',"%{$search}%")
                  ->orWhere('card','like',"%{$search}%")
                  ->orWhere('USER_ADDR','like',"%{$search}%")
                  ->orWhereHas('user', fn($uq)=>$uq->where('NAME','like',"%{$search}%"));
            });
        }
        if ($statFilter !== null && in_array($statFilter, ['0','1'], true)) {
            $qUser->where('stat', (int) $statFilter);
        }
        $userItems = $qUser->get();

        $rows = collect();
        foreach ($userItems as $u) {
            $m = strtoupper($u->scan_method ?: 'card');
            $rows->push((object)[
                'time' => $u->TM_EVENT,
                'source' => 'Hardware Device',
                'device_name' => $u->device?->name ?? '-',
                'sn' => $u->DEVICESN,
                'user' => ($u->user?->NAME ?? "User #{$u->USER_ADDR}"),
                'method' => $m,
                'card' => $u->card ?: ($u->user?->Card ?? '-'),
                'stat' => (int)$u->stat === 1 ? 'OUT' : 'IN',
                'result' => '✓ SUCCESS (Tap Recorded)',
                'detail' => 'Hardware Log',
            ]);
        }

        $sortedRows = $rows->sortByDesc(fn($r)=>$r->time ? $r->time->timestamp : 0)->values();
        $fileName = 'Log Access'.date('Ymd_His').'.xls';

        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="UTF-8"><style>td{font-family:Calibri,Arial,sans-serif;font-size:11px} .hdr{background:#DC2626;color:#FFFFFF;font-weight:bold;text-align:center;border:.5pt solid #1F2937} .cell{border:.5pt solid #D1D5DB;padding:4px} .zebra{background:#F9FAFB} .ctr{text-align:center} .hdr2{background:#DC2626;color:#FFFFFF;font-weight:bold;text-align:center;font-size:14px;padding:10px}</style></head><body>';
        $html .= '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;width:100%">';
        $html .= '<tr><td colspan="10" class="hdr2">Log Aktivitas Gate Hardware (tbl_userlog) — '.$titlePeriode.' — Total: '.$sortedRows->count().'</td></tr>';
        $html .= '<tr><td class="hdr">#</td><td class="hdr">Waktu</td><td class="hdr">Sumber</td><td class="hdr">Device Name</td><td class="hdr">SN</td><td class="hdr">Member / Pengguna</td><td class="hdr">Metode</td><td class="hdr">No. Kartu</td><td class="hdr">IN / OUT</td><td class="hdr">Hasil / Akses</td></tr>';

        $n=1;
        foreach($sortedRows as $row){
            $cls = ($n%2===0) ? 'zebra' : '';
            $timeStr = $row->time ? $row->time->format('Y-m-d H:i:s') : '-';
            $html .= "<tr class=\"{$cls}\"><td class=\"cell ctr\">{$n}</td><td class=\"cell ctr\">{$timeStr}</td><td class=\"cell ctr font-bold\">{$row->source}</td><td class=\"cell\">".htmlspecialchars($row->device_name)."</td><td class=\"cell ctr\">".htmlspecialchars($row->sn)."</td><td class=\"cell font-bold\">".htmlspecialchars($row->user)."</td><td class=\"cell ctr\">{$row->method}</td><td class=\"cell ctr\">".htmlspecialchars($row->card)."</td><td class=\"cell ctr\">{$row->stat}</td><td class=\"cell\">{$row->result}</td></tr>";
            $n++;
        }

        if($sortedRows->isEmpty()){
            $html .= '<tr><td colspan="10" class="cell ctr" style="padding:20px;color:#9CA3AF">Tidak ada data log sesuai filter</td></tr>';
        }
        $html .= '</table></body></html>';

        return response($html)->header('Content-Type','application/vnd.ms-excel; charset=UTF-8')->header('Content-Disposition','attachment; filename="'.$fileName.'"')->header('Cache-Control','max-age=0');
    }
}
