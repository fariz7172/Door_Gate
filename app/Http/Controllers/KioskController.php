<?php
namespace App\Http\Controllers;
use App\Models\Door;

class KioskController extends Controller {
    public function show(string $door_code){
        $door = Door::where('door_code', $door_code)->firstOrFail();
        return view('kiosk.scanner', compact('door'));
    }
}
