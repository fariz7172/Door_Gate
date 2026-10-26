<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Door;
use App\Services\DoorService;
class DoorApiController extends Controller {
    public function open(Door $door, DoorService $svc){
        if(!$door->is_active) return response()->json(['ok'=>false,'reason'=>'Door inactive'], 403);
        $ok = $svc->pulse($door);
        return response()->json(['ok'=>$ok, 'door'=>$door->door_code]);
    }
}
