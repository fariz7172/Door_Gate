<?php
namespace App\Http\Controllers;
use App\Models\ApiEndpoint;
use App\Services\DeviceGatewayService;
use Illuminate\Http\Request;

class ApiEndpointController extends Controller {
    public function index(Request $r){
        $q = ApiEndpoint::query();
        if ($r->filled('group')) $q->where('group', $r->group);
        if ($r->filled('search')) {
            $s = $r->search;
            $q->where(function($qq) use($s){
                $qq->where('url','like',"%{$s}%")->orWhere('desc','like',"%{$s}%");
            });
        }
        if ($r->filled('status')) {
            $q->where('is_active', $r->status==='active');
        }
        $endpoints = $q->orderBy('group')->orderBy('id')->paginate(20)->withQueryString();
        $groups = ApiEndpoint::selectRaw('`group`, count(*) as c')->groupBy('group')->pluck('c','group');
        $stats = [
            'total' => ApiEndpoint::count(),
            'active' => ApiEndpoint::where('is_active',true)->count(),
            'soyal' => ApiEndpoint::where('group','soyal')->count(),
            'tasoft' => ApiEndpoint::where('group','tasoft')->count(),
        ];
        return view('endpoints.index', compact('endpoints','groups','stats'));
    }

    public function create(){
        return view('endpoints.form', ['ep'=> new ApiEndpoint(['http_method'=>'POST','group'=>'tasoft','is_active'=>true]), 'mode'=>'create']);
    }

    public function store(Request $r){
        $data = $r->validate([
            'url' => 'required|url|max:255',
            'desc' => 'required|max:255',
            'group' => 'required|in:soyal,tasoft,dreampos,device,system,general',
            'http_method' => 'required|in:GET,POST,PUT,PATCH,DELETE',
            'default_payload' => 'nullable|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
        if (!empty($data['default_payload'])) {
            json_decode($data['default_payload']);
            if (json_last_error()!==JSON_ERROR_NONE) return back()->withErrors(['default_payload'=>'JSON tidak valid: '.json_last_error_msg()])->withInput();
        }
        $data['is_active'] = $r->has('is_active');
        ApiEndpoint::create($data);
        return redirect()->route('endpoints.index')->with('ok','Endpoint ditambahkan');
    }

    public function edit(ApiEndpoint $endpoint){
        return view('endpoints.form', ['ep'=>$endpoint, 'mode'=>'edit']);
    }

    public function update(Request $r, ApiEndpoint $endpoint){
        $data = $r->validate([
            'url' => 'required|url|max:255',
            'desc' => 'required|max:255',
            'group' => 'required|in:soyal,tasoft,dreampos,device,system,general',
            'http_method' => 'required|in:GET,POST,PUT,PATCH,DELETE',
            'default_payload' => 'nullable|max:2000',
            'is_active' => 'nullable|boolean',
        ]);
        if (!empty($data['default_payload'])) {
            json_decode($data['default_payload']);
            if (json_last_error()!==JSON_ERROR_NONE) return back()->withErrors(['default_payload'=>'JSON tidak valid: '.json_last_error_msg()])->withInput();
        }
        $data['is_active'] = $r->has('is_active');
        $endpoint->update($data);
        return redirect()->route('endpoints.index')->with('ok','Endpoint diperbarui');
    }

    public function destroy(ApiEndpoint $endpoint){
        $endpoint->delete();
        return back()->with('ok','Endpoint dihapus');
    }

    // proxy test: POST /endpoints/{endpoint}/test
    public function test(Request $r, ApiEndpoint $endpoint, DeviceGatewayService $gw){
        $payload = [];
        if ($r->filled('payload')) {
            $decoded = json_decode($r->payload, true);
            if (json_last_error()!==JSON_ERROR_NONE) return response()->json(['ok'=>false,'error'=>'Payload JSON invalid: '.json_last_error_msg()], 422);
            $payload = $decoded;
        }
        $result = $gw->call($endpoint->id, $payload);
        return response()->json($result);
    }

    // seed from original `api` table data (18 rows)
    public function seed(Request $r){
        $rows = [
            [1,'http://localhost:8000/api/soyal/book/v1/1/adduser','URL ADD USER SOYAL MECHINE'],
            [2,'http://localhost:787/sztimmy/registerface','ADD USER RESGISTER TASOFT'],
            [3,'http://localhost:787/sztimmy/useraccess','User Access Tasoft'],
            [4,'http://localhost:787/sztimmy/getuserlist','Get User List Tasoft'],
            [5,'http://127.0.0.1:8000/api/soyal/book/v1/1/deleteuser','Delete User Soyal Mechine'],
            [6,'http://localhost:787/sztimmy/deleteface','Delete User Tasoft'],
            [7,'http://localhost:8080/api/members','Sync Member Dreampos'],
            [8,'http://localhost:8080/api/view-members','Get Data Member Dreampos'],
            [9,'http://localhost:787/sztimmy/opengate','Opengate'],
            [10,'http://localhost:787/sztimmy/cleanlogs','CleanLog'],
            [11,'http://localhost:787/sztimmy/gettime','Gettime'],
            [12,'http://localhost:787/sztimmy/settime','SetTime'],
            [13,'http://localhost:787/sztimmy/reboot','reboot'],
            [14,'http://localhost:787/sztimmy/dayzone','dayzone'],
            [15,'http://localhost:787/sztimmy/weekzone','weekzone'],
            [16,'http://localhost:787/sztimmy/userWeekzone','userweekzone'],
            [17,'http://localhost:787/sztimmy/enabledevice','EnableDevice'],
            [18,'http://localhost:787/sztimmy/disabledevice','DisableDevice'],
        ];
        $created = 0;
        foreach($rows as [$id,$url,$desc]){
            $group = ApiEndpoint::detectGroup($url, $desc);
            $exists = ApiEndpoint::where('url',$url)->exists();
            if (!$exists) {
                ApiEndpoint::create(['url'=>$url,'desc'=>$desc,'group'=>$group,'http_method'=>'POST','is_active'=>true]);
                $created++;
            }
        }
        return back()->with('ok', "Seed selesai — {$created} endpoint baru ditambahkan (total ".ApiEndpoint::count().")");
    }
}
