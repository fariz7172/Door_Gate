<?php
namespace App\Http\Controllers;

use App\Models\ApiEndpoint;
use App\Models\DeviceGate;
use App\Services\DeviceGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DeviceGateController extends Controller
{
    public function index(Request $r){
        $q = DeviceGate::query();
        if ($r->filled('search')) {
            $s = $r->search;
            $q->where(function($qq) use($s){
                $qq->where('name','like',"%{$s}%")
                   ->orWhere('ip','like',"%{$s}%")
                   ->orWhere('sn','like',"%{$s}%");
            });
        }
        if ($r->filled('type') && $r->type !== '') $q->where('type', $r->type);
        if ($r->filled('flagstatus') && $r->flagstatus !== '') $q->where('flagstatus', $r->flagstatus);
        if ($r->filled('stat') && $r->stat !== '') $q->where('stat', $r->stat);
        $devices = $q->orderBy('id','desc')->paginate(12)->withQueryString();
        $stats = [
            'total' => DeviceGate::count(),
            'connected' => DeviceGate::where('flagstatus',1)->count(),
            'disconnected' => DeviceGate::where('flagstatus',0)->count(),
            'face' => DeviceGate::where('type',50)->count(),
            'finger' => DeviceGate::where('type',0)->count(),
            'soyal' => DeviceGate::where('type',1)->count(),
        ];
        $allEnabled = DeviceGate::count() > 0 && DeviceGate::where('is_enabled', 0)->count() === 0;
        return view('devicegate.index', compact('devices','stats','allEnabled'));
    }

    public function create(){
        return view('devicegate.form', ['d'=> new DeviceGate(['flagstatus'=>1,'type'=>50,'stat'=>0,'number'=>0,'nodeid'=>0,'groupid'=>0,'locationid'=>0,'Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0]), 'mode'=>'create']);
    }

    public function store(Request $r){
        $data = $r->validate([
            'name' => 'required|max:255',
            'number' => 'nullable|integer',
            'flagstatus' => 'required|in:0,1',
            'type' => 'required|integer',
            'sn' => 'nullable|max:15',
            'ip' => 'nullable|max:20',
            'nodeid' => 'nullable|integer',
            'description' => 'required|string|max:500',
            'groupid' => 'nullable|integer',
            'stat' => 'required|in:0,1',
            'Lift1' => 'nullable|integer|min:0|max:255',
            'Lift2' => 'nullable|integer|min:0|max:255',
            'Lift3' => 'nullable|integer|min:0|max:255',
            'Lift4' => 'nullable|integer|min:0|max:255',
            'locationid' => 'nullable|integer',
        ]);
        $data['number'] = $data['number'] ?? 0;
        $data['nodeid'] = $data['nodeid'] ?? 0;
        $data['groupid'] = $data['groupid'] ?? 0;
        $data['locationid'] = $data['locationid'] ?? 0;
        foreach(['Lift1','Lift2','Lift3','Lift4'] as $k){ if(!isset($data[$k])) $data[$k]=0; }
        DeviceGate::create($data);
        return redirect()->route('devicegate.index')->with('ok','Device ditambahkan');
    }

    public function edit(DeviceGate $devicegate){
        return view('devicegate.form', ['d'=>$devicegate, 'mode'=>'edit']);
    }

    public function update(Request $r, DeviceGate $devicegate){
        $data = $r->validate([
            'name' => 'required|max:255',
            'number' => 'nullable|integer',
            'flagstatus' => 'required|in:0,1',
            'type' => 'required|integer',
            'sn' => 'nullable|max:15',
            'ip' => 'nullable|max:20',
            'nodeid' => 'nullable|integer',
            'description' => 'required|string|max:500',
            'groupid' => 'nullable|integer',
            'stat' => 'required|in:0,1',
            'Lift1' => 'nullable|integer|min:0|max:255',
            'Lift2' => 'nullable|integer|min:0|max:255',
            'Lift3' => 'nullable|integer|min:0|max:255',
            'Lift4' => 'nullable|integer|min:0|max:255',
            'locationid' => 'nullable|integer',
        ]);
        $data['number'] = $data['number'] ?? 0;
        $data['nodeid'] = $data['nodeid'] ?? 0;
        $data['groupid'] = $data['groupid'] ?? 0;
        $data['locationid'] = $data['locationid'] ?? 0;
        $devicegate->update($data);
        return redirect()->route('devicegate.index')->with('ok','Device diperbarui');
    }

    public function destroy(DeviceGate $devicegate){
        $devicegate->delete();
        return back()->with('ok','Device dihapus');
    }

    // === Tasoft actions — fixed mapping per spec ===
    // id 9  = opengate   payload {sn}
    // id 10 = cleanlogs  payload {sn}
    // id 11 = gettime    payload {sn}
    // id 12 = settime    payload {sn, Time: "Y-m-d H:i:s"}
    // id 13 = reboot     payload {sn}
    private function mustEndpoint(int $id): ApiEndpoint {
        return ApiEndpoint::findOrFail($id);
    }

    public function open(DeviceGate $devicegate){
        $ep = $this->mustEndpoint(9);
        $res = app(DeviceGatewayService::class)->call($ep->id, ['sn' => $devicegate->sn]);
        return response()->json($res);
    }
    public function cleanLogs(DeviceGate $devicegate){
        $ep = $this->mustEndpoint(10);
        $res = app(DeviceGatewayService::class)->call($ep->id, ['sn' => $devicegate->sn]);
        return response()->json($res);
    }
    public function getTime(DeviceGate $devicegate){
        if(empty($devicegate->sn)){
            return response()->json(['ok' => false, 'status' => 400, 'message' => 'SN mesin kosong', 'is_connected' => false]);
        }

        // Cek langsung status soket realtime di daemon gateway (tanpa mengganggu atau memutus hardware)
        $isOnline = false;
        try {
            $resp = Http::timeout(2)->get('http://127.0.0.1:787/sztimmy/active-devices');
            if ($resp->successful()) {
                $activeSns = $resp->json('registered_devices') ?? [];
                $isOnline = in_array($devicegate->sn, $activeSns, true);
            }
        } catch (\Throwable $e) {}

        $devicegate->update(['flagstatus' => $isOnline ? 1 : 0]);

        return response()->json([
            'ok' => true,
            'status' => $isOnline ? 200 : 404,
            'sn' => $devicegate->sn,
            'is_connected' => $isOnline,
            'message' => $isOnline ? "Mesin SN [{$devicegate->sn}] terhubung aktif di server gateway." : "Mesin SN [{$devicegate->sn}] sedang offline.",
        ]);
    }

    /**
     * Batch Cek Status Semua Device (dipanggil otomatis saat halaman dibuka)
     */
    public function pingAll(){
        $devices = DeviceGate::all();
        $results = [];

        // Tanya langsung ke daemon via active-devices (cepat, 0ms, tidak mematikan koneksi hardware!)
        $activeSns = [];
        try {
            $resp = Http::timeout(2)->get('http://127.0.0.1:787/sztimmy/active-devices');
            if ($resp->successful()) {
                $activeSns = $resp->json('registered_devices') ?? [];
            }
        } catch (\Throwable $e) {}

        foreach($devices as $d){
            if(empty($d->sn)){
                $results[$d->id] = ['id' => $d->id, 'sn' => '', 'ok'=>false, 'is_connected'=>false, 'reason'=>'SN kosong'];
                continue;
            }
            $isOnline = in_array($d->sn, $activeSns, true);
            $d->update(['flagstatus' => $isOnline ? 1 : 0]);
            $results[$d->id] = [
                'id' => $d->id,
                'sn' => $d->sn,
                'ok' => true,
                'status' => $isOnline ? 200 : 404,
                'is_connected' => $isOnline,
            ];
        }

        return response()->json([
            'status' => 'success',
            'checked_count' => count($results),
            'devices' => $results,
        ]);
    }
    public function setTime(Request $r, DeviceGate $devicegate){
        $r->validate(['Time' => 'required|string|max:30']);
        $ep = $this->mustEndpoint(12);
        $res = app(DeviceGatewayService::class)->call($ep->id, [
            'sn' => $devicegate->sn,
            'Time' => $r->input('Time'),
        ]);
        return response()->json($res);
    }
    public function reboot(DeviceGate $devicegate){
        $ep = $this->mustEndpoint(13);
        $res = app(DeviceGatewayService::class)->call($ep->id, ['sn' => $devicegate->sn]);
        return response()->json($res);
    }
    public function enable(DeviceGate $devicegate){
        $ep = ApiEndpoint::find(17); // enabledevice
        if($ep){
            $res = app(DeviceGatewayService::class)->call($ep->id, ['sn'=>$devicegate->sn]);
            $bodyOk = is_array($res['body'] ?? null) && (($res['body']['status'] ?? '') === 'success');
            if (($res['ok'] ?? false) && $bodyOk) {
                $devicegate->update(['is_enabled' => 1]);
                return response()->json(['ok'=>true,'msg'=>'Diaktifkan','is_enabled'=>1]);
            }
            $msg = is_array($res['body'] ?? null) ? ($res['body']['message'] ?? 'Gagal') : 'Gagal';
            return response()->json(['ok'=>false,'msg'=>"Gagal mengaktifkan: {$msg}",'is_enabled'=>(int)$devicegate->is_enabled]);
        }
        $devicegate->update(['is_enabled' => 1]);
        return response()->json(['ok'=>true,'msg'=>'Diaktifkan','is_enabled'=>1]);
    }

    public function disable(DeviceGate $devicegate){
        $ep = ApiEndpoint::find(18); // disabledevice
        if($ep){
            $res = app(DeviceGatewayService::class)->call($ep->id, ['sn'=>$devicegate->sn]);
            $bodyOk = is_array($res['body'] ?? null) && (($res['body']['status'] ?? '') === 'success');
            if (($res['ok'] ?? false) && $bodyOk) {
                $devicegate->update(['is_enabled' => 0]);
                return response()->json(['ok'=>true,'msg'=>'Dinonaktifkan','is_enabled'=>0]);
            }
            $msg = is_array($res['body'] ?? null) ? ($res['body']['message'] ?? 'Gagal') : 'Gagal';
            return response()->json(['ok'=>false,'msg'=>"Gagal menonaktifkan: {$msg}",'is_enabled'=>(int)$devicegate->is_enabled]);
        }
        $devicegate->update(['is_enabled' => 0]);
        return response()->json(['ok'=>true,'msg'=>'Dinonaktifkan','is_enabled'=>0]);
    }

    public function toggleAll(Request $r){
        $status = (int)$r->input('is_enabled', 1);
        $epId = $status === 1 ? 17 : 18; // enabledevice (17) / disabledevice (18)
        $ep = ApiEndpoint::find($epId);
        
        $devices = DeviceGate::all();
        $updated = 0;
        
        foreach($devices as $d){
            if($ep && !empty($d->sn)){
                try {
                    app(DeviceGatewayService::class)->call($ep->id, ['sn' => $d->sn]);
                } catch(\Throwable $e){}
            }
            $d->update(['is_enabled' => $status]);
            $updated++;
        }

        $label = $status === 1 ? 'diaktifkan' : 'dinonaktifkan';
        return response()->json([
            'ok' => true,
            'is_enabled' => $status,
            'msg' => "Semua {$updated} device berhasil {$label}",
            'count' => $updated,
        ]);
    }

    public function seed(){
        $samples = [
            ['name'=>'Gate Utama - Lobby','number'=>1,'flagstatus'=>1,'type'=>50,'sn'=>'ZXRL23103798','ip'=>'192.168.1.50','nodeid'=>0,'description'=>'Face - Pintu Masuk Utama','groupid'=>1,'stat'=>0,'Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0,'locationid'=>1],
            ['name'=>'Gate Keluar - Lobby','number'=>2,'flagstatus'=>1,'type'=>50,'sn'=>'ZYRL19100733','ip'=>'192.168.1.51','nodeid'=>0,'description'=>'Face - Pintu Keluar','groupid'=>1,'stat'=>1,'Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0,'locationid'=>1],
            ['name'=>'Soyal Door - VIP','number'=>3,'flagstatus'=>0,'type'=>1,'sn'=>'AYSK17105912','ip'=>'192.168.1.60','nodeid'=>1,'description'=>'Soyal - Ruang VIP','groupid'=>2,'stat'=>0,'Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0,'locationid'=>2],
            ['name'=>'Finger - Staff','number'=>4,'flagstatus'=>1,'type'=>0,'sn'=>'ZYSL20032920','ip'=>'192.168.1.70','nodeid'=>0,'description'=>'Finger - Pintu Staff','groupid'=>1,'stat'=>0,'Lift1'=>0,'Lift2'=>0,'Lift3'=>0,'Lift4'=>0,'locationid'=>3],
        ];
        $added=0;
        foreach($samples as $row){
            if(!DeviceGate::where('sn',$row['sn'])->exists()){
                DeviceGate::create($row); $added++;
            }
        }
        return back()->with('ok',"Seed device selesai — {$added} baru (total ".DeviceGate::count().")");
    }
}
