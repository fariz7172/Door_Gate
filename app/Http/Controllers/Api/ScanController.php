<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\AccessLog;
use App\Models\Door;
use App\Services\DoorService;
use App\Services\FitQuartersService;
use Illuminate\Http\Request;

class ScanController extends Controller {
    public function store(Request $r, FitQuartersService $fq, DoorService $doorSvc){
        $data = $r->validate([
            'door_code'=>'required|string',
            'member_id'=>'required|string|max:100',
            'type'=>'nullable|in:in,out,check_in,check_out',
        ]);
        $door = Door::where('door_code', $data['door_code'])->first();
        if(!$door) return response()->json(['status'=>'error','reason'=>'Door not found'], 404);
        if(!$door->is_active){
            $log = AccessLog::create(['door_id'=>$door->id,'member_id'=>$data['member_id'],'scan_type'=>'check_in','status'=>'denied','reason'=>'Door inactive','scanned_at'=>now()]);
            return response()->json(['status'=>'denied','reason'=>'Door inactive / maintenance','log_id'=>$log->id]);
        }
        $type = $data['type'] ?? 'in';
        $isOut = in_array($type, ['out','check_out']);
        $deviceId = $door->door_code;
        try {
            if($isOut){
                $res = $fq->checkOut($data['member_id'], $deviceId);
                $doorSvc->pulse($door);
                $log = AccessLog::create(['door_id'=>$door->id,'member_id'=>$data['member_id'],'member_name'=>$res['member_id'] ?? null,'scan_type'=>'check_out','status'=>'granted','reason'=>'OK','fitquarters_event_id'=>$res['id'] ?? null,'scanned_at'=>now()]);
                return response()->json(['status'=>'granted','member_name'=> $data['member_id'], 'fitquarters'=>$res, 'log_id'=>$log->id]);
            } else {
                $res = $fq->checkIn($data['member_id'], $deviceId);
                $doorSvc->pulse($door);
                $log = AccessLog::create(['door_id'=>$door->id,'member_id'=>$data['member_id'],'member_name'=>$res['member_id'] ?? null,'scan_type'=>'check_in','status'=>'granted','reason'=>'OK','fitquarters_event_id'=>$res['id'] ?? null,'scanned_at'=>now()]);
                return response()->json(['status'=>'granted','member_name'=> $data['member_id'], 'fitquarters'=>$res, 'log_id'=>$log->id]);
            }
        } catch (\Throwable $e){
            $msg = $e->getMessage();
            $isDenied = str_contains($msg,'not_found') || str_contains($msg,'invalid') || str_contains($msg,'expired') || $e->getCode()==403 || $e->getCode()==404;
            $status = $isDenied ? 'denied' : 'error';
            $log = AccessLog::create(['door_id'=>$door->id,'member_id'=>$data['member_id'],'scan_type'=> $isOut ? 'check_out':'check_in','status'=>$status,'reason'=> mb_substr($msg,0,250),'scanned_at'=>now()]);
            $code = $isDenied ? 200 : 500;
            return response()->json(['status'=>$status,'reason'=> mb_substr($msg,0,300), 'log_id'=>$log->id], $code);
        }
    }
    public function latest(Request $r){
        $logs = AccessLog::with('door')->latestFirst()->limit((int)$r->get('limit',10))->get();
        return response()->json($logs);
    }
}
