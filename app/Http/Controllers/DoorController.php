<?php
namespace App\Http\Controllers;
use App\Models\Door;
use Illuminate\Http\Request;

class DoorController extends Controller {
    public function index(){ $doors = Door::orderBy('name')->get(); return view('doors.index', compact('doors')); }
    public function create(){ return view('doors.form', ['door'=> new Door(), 'mode'=>'create']); }
    public function store(Request $r){
        $data = $r->validate([
            'door_code'=>'required|unique:doors,door_code|regex:/^[a-z0-9\-]+$/|max:50',
            'name'=>'required|max:100',
            'direction'=>'required|in:in,out,bidirectional',
            'relay_channel'=>'required|integer|min:1|max:16',
            'pulse_duration_ms'=>'required|integer|min:500|max:15000',
            'is_active'=>'nullable|boolean',
        ]);
        $data['is_active'] = $r->has('is_active');
        Door::create($data);
        return redirect()->route('doors.index')->with('ok','Pintu berhasil ditambahkan');
    }
    public function edit(Door $door){ return view('doors.form', ['door'=>$door,'mode'=>'edit']); }
    public function update(Request $r, Door $door){
        $data = $r->validate([
            'door_code'=>'required|regex:/^[a-z0-9\-]+$/|max:50|unique:doors,door_code,'.$door->id,
            'name'=>'required|max:100',
            'direction'=>'required|in:in,out,bidirectional',
            'relay_channel'=>'required|integer|min:1|max:16',
            'pulse_duration_ms'=>'required|integer|min:500|max:15000',
            'is_active'=>'nullable|boolean',
        ]);
        $data['is_active'] = $r->has('is_active');
        $door->update($data);
        return redirect()->route('doors.index')->with('ok','Pintu diperbarui');
    }
    public function destroy(Door $door){ $door->delete(); return back()->with('ok','Pintu dihapus'); }
}
