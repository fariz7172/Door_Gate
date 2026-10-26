<?php
namespace App\Http\Controllers;
use App\Models\GymSetting;
use App\Services\FitQuartersService;
use Illuminate\Http\Request;

class SettingsController extends Controller {
    public function edit(){
        $setting = GymSetting::current();
        $health = app(FitQuartersService::class)->healthCheck();
        return view('settings.edit', compact('setting','health'));
    }
    public function update(Request $r){
        $data = $r->validate([
            'gym_name'=>'required|max:100',
            'api_base_url'=>'required|url',
            'api_key'=>'required|max:255',
            'relay_mode'=>'required|in:http,mqtt,serial,dummy',
            'relay_ip'=>'nullable|max:100',
            'default_device_id'=>'required|max:50',
        ]);
        $s = GymSetting::current();
        $s->update($data);
        // also update config runtime
        return back()->with('ok','Pengaturan disimpan. '.(app(FitQuartersService::class)->healthCheck() ? 'Koneksi FitQuarters OK' : 'Koneksi FitQuarters gagal cek'));
    }
}
