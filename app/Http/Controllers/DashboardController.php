<?php
namespace App\Http\Controllers;

use App\Models\DeviceGate;
use App\Models\TblUserlog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller {
    public function index(Request $r){
        $filter = $r->get('filter','today');
        $statFilter = $r->get('stat');
        $search = $r->get('q');
        
        $q = TblUserlog::with(['user', 'device'])->orderByDesc('REC_INDEX');
        
        $today = now()->format('Y-m-d');
        if($filter === 'today') {
            $q->whereBetween('TM_EVENT', [$today . ' 00:00:00', $today . ' 23:59:59']);
        }
        
        if($statFilter !== null && in_array($statFilter, ['0','1'], true)) {
            $q->where('stat', (int)$statFilter);
        }

        if(!empty($search)) {
            $q->where(function($w) use ($search){
                $w->where('DEVICESN', 'like', "%{$search}%")
                  ->orWhere('card', 'like', "%{$search}%")
                  ->orWhere('USER_ADDR', 'like', "%{$search}%")
                  ->orWhereHas('user', function($uq) use ($search){
                      $uq->where('NAME', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $q->paginate(15)->withQueryString();

        $logs->getCollection()->transform(function($ul){
            $m = strtolower((string)($ul->scan_method ?: 'card'));
            $mLabel = $m === 'face' ? 'Face' : ($m === 'finger' ? 'Finger' : 'RFID Card');
            return (object) [
                'type' => 'userlog',
                'raw_id' => $ul->REC_INDEX,
                'uid' => 'user_' . $ul->REC_INDEX,
                'time' => $ul->TM_EVENT,
                'sn' => $ul->DEVICESN,
                'device_name' => $ul->device?->name ?: ($ul->DEVICESN ?: 'Turnstile Gate'),
                'user_id' => $ul->USER_ADDR,
                'user_name' => $ul->user?->NAME ?? ('User #' . $ul->USER_ADDR),
                'card_no' => $ul->card ?: ($ul->user?->Card ?? '—'),
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

        $deviceGates = Cache::remember('devicegates:list', 60, fn()=> DeviceGate::orderBy('name')->get());

        // Hitung statistik hari ini murni dari TblUserlog
        $todayStats = TblUserlog::whereBetween('TM_EVENT', [$today . ' 00:00:00', $today . ' 23:59:59'])
            ->selectRaw("COUNT(*) as total, SUM(CASE WHEN stat=0 THEN 1 ELSE 0 END) as in_cnt, SUM(CASE WHEN stat=1 THEN 1 ELSE 0 END) as out_cnt")
            ->first();

        $stats = [
            'total_tap'     => (int)($todayStats->total ?? 0),
            'tap_in'        => (int)($todayStats->in_cnt ?? 0),
            'tap_out'       => (int)($todayStats->out_cnt ?? 0),
            'doors_active'  => DeviceGate::where('flagstatus', 1)->count(),
            'gates_total'   => $deviceGates->count(),
        ];

        $setting = (object)[
            'api_base_url' => config('app.url', 'http://127.0.0.1:8090'),
            'default_device_id' => 'AYUH22014745',
            'relay_mode' => 'tcp_socket',
        ];
        $health = true;

        $lastLogId = TblUserlog::max('REC_INDEX') ?? 0;

        return view('dashboard.index', compact('logs', 'stats', 'deviceGates', 'setting', 'health', 'filter', 'lastLogId'));
    }
}
