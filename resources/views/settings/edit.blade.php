@extends('layouts.app')
@section('title','Pengaturan')
@section('breadcrumb','Sistem / Pengaturan')
@section('content')
<div class="max-w-[640px]">
  <h2 class="font-serif text-[26px] leading-none tracking-tight">Pengaturan <span class="font-space font-bold text-fq-600">Gateway</span></h2>
  <p class="mt-2 text-sm text-zinc-500 leading-relaxed">Koneksi ke <span class="font-mono text-xs bg-zinc-100 px-1.5 py-0.5 rounded border border-zinc-200">api.fitquarters.id</span> via <span class="font-mono text-xs bg-red-50 border border-red-200 px-1.5 py-0.5 rounded text-fq-700">Bearer fqk_…</span> — health di sidebar ikut status ini.</p>

  <div class="mt-4 flex items-center gap-2.5 glass rounded-2xl px-4 py-3">
    <span class="h-2.5 w-2.5 rounded-full shrink-0 {{ $health ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.6)]' : 'bg-red-500 animate-pulse' }}"></span>
    <span class="text-sm font-bold {{ $health ? 'text-emerald-700' : 'text-red-700' }}">{{ $health ? 'Terhubung' : 'Terputus' }}</span>
    <span class="text-xs text-zinc-500">— test GET /v1/check_ins?limit=1 · {{ $health ? '200 OK' : 'periksa API key' }}</span>
  </div>

  <form method="POST" action="{{ route('settings.update') }}" class="glass rounded-[24px] p-6 sm:p-7 mt-6 space-y-4">
    @csrf @method('PUT')
    @if($errors->any())<div class="rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">{{ $errors->first() }}</div>@endif
    <label class="block">
      <span class="text-sm font-bold">Nama Gym</span>
      <input name="gym_name" value="{{ old('gym_name',$setting->gym_name) }}" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <label class="block">
      <span class="text-sm font-bold">API Base URL</span>
      <input name="api_base_url" value="{{ old('api_base_url',$setting->api_base_url) }}" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <label class="block">
      <span class="text-sm font-bold">API Key <span class="text-fq-600">*</span> — Bearer fqk_&lt;hex&gt;</span>
      <input name="api_key" value="{{ old('api_key',$setting->api_key) }}" required placeholder="fqk_..." class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      <span class="text-xs text-zinc-500">Key aktif: <span class="font-mono bg-zinc-100 px-1 py-0.5 rounded border border-zinc-200 text-[11px]">fqk_ccd5…6009b</span> (dari Postman collection)</span>
    </label>
    <div class="grid grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm font-bold">Relay Mode</span>
        <select name="relay_mode" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
          <option value="dummy" {{ $setting->relay_mode=='dummy'?'selected':'' }}>Dummy (log only)</option>
          <option value="http" {{ $setting->relay_mode=='http'?'selected':'' }}>HTTP Relay</option>
          <option value="mqtt" {{ $setting->relay_mode=='mqtt'?'selected':'' }}>MQTT</option>
          <option value="serial" {{ $setting->relay_mode=='serial'?'selected':'' }}>Serial</option>
        </select>
      </label>
      <label class="block">
        <span class="text-sm font-bold">Relay IP / Host</span>
        <input name="relay_ip" value="{{ old('relay_ip',$setting->relay_ip) }}" placeholder="192.168.1.200" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      </label>
    </div>
    <label class="block">
      <span class="text-sm font-bold">Default Device ID</span>
      <input name="default_device_id" value="{{ old('default_device_id',$setting->default_device_id) }}" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      <span class="text-xs text-zinc-500">Dipakai kalau pintu tidak kirim device_id — default <span class="font-mono">front-gate-1</span></span>
    </label>
    <button class="w-full py-3 rounded-full bg-fq-600 text-white font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">Simpan Pengaturan</button>
  </form>
</div>
@endsection
