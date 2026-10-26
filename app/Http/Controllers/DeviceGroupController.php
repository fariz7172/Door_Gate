<?php
namespace App\Http\Controllers;
use App\Models\DeviceGate;
use App\Models\DeviceGroup;
use Illuminate\Http\Request;

class DeviceGroupController extends Controller {
    public function index(Request $r){
        $q = DeviceGroup::withCount('deviceGates');
        if($r->filled('search')){
            $s=$r->search;
            $q->where(fn($qq)=>$qq->where('name','like',"%{$s}%")->orWhere('description','like',"%{$s}%")->orWhere('number','like',"%{$s}%"));
        }
        $groups = $q->orderBy('number')->paginate(12)->withQueryString();
        $stats = ['total'=>DeviceGroup::count()];
        return view('devicegroup.index', compact('groups','stats'));
    }
    public function create(){
        $g = new DeviceGroup();
        $deviceGates = DeviceGate::orderBy('name')->get();
        $selected = [];
        return view('devicegroup.form', ['g'=>$g, 'mode'=>'create', 'deviceGates'=>$deviceGates, 'selected'=>$selected]);
    }
    public function store(Request $r){
        $data=$r->validate([
            'number'=>'nullable|max:11',
            'name'=>'nullable|max:30',
            'description'=>'nullable|max:1000',
            'device_ids'=>'nullable|array',
            'device_ids.*'=>'exists:devicegate,id',
        ]);
        $deviceIds = $data['device_ids'] ?? [];
        unset($data['device_ids']);
        $group = DeviceGroup::create($data);
        if(!empty($deviceIds)){
            DeviceGate::whereIn('id', $deviceIds)->update(['groupid'=>$group->id]);
        }
        return redirect()->route('devicegroup.index')->with('ok','Device group ditambahkan' . (!empty($deviceIds) ? ' — '.count($deviceIds).' device terhubung' : ''));
    }
    public function edit(DeviceGroup $devicegroup){
        $deviceGates = DeviceGate::orderBy('name')->get();
        $selected = DeviceGate::where('groupid', $devicegroup->id)->pluck('id')->toArray();
        return view('devicegroup.form', ['g'=>$devicegroup,'mode'=>'edit', 'deviceGates'=>$deviceGates, 'selected'=>$selected]);
    }
    public function update(Request $r, DeviceGroup $devicegroup){
        $data=$r->validate([
            'number'=>'nullable|max:11',
            'name'=>'nullable|max:30',
            'description'=>'nullable|max:1000',
            'device_ids'=>'nullable|array',
            'device_ids.*'=>'exists:devicegate,id',
        ]);
        $deviceIds = $data['device_ids'] ?? [];
        unset($data['device_ids']);
        $devicegroup->update($data);
        // sync: lepaskan yang lama yang tidak dicentang, assign yang baru
        DeviceGate::where('groupid', $devicegroup->id)->whereNotIn('id', $deviceIds ?: [0])->update(['groupid'=>0]);
        if(!empty($deviceIds)){
            DeviceGate::whereIn('id', $deviceIds)->update(['groupid'=>$devicegroup->id]);
        }
        return redirect()->route('devicegroup.index')->with('ok','Device group diperbarui' . (!empty($deviceIds) ? ' — '.count($deviceIds).' device' : ''));
    }
    public function destroy(DeviceGroup $devicegroup){
        DeviceGate::where('groupid', $devicegroup->id)->update(['groupid'=>0]);
        $devicegroup->delete();
        return back()->with('ok','Device group dihapus — device dilepas');
    }
}
