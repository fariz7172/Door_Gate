@extends('layouts.app')
@section('title', ($mode==='create' ? 'Tambah' : 'Edit').' Pintu')
@section('breadcrumb','Perangkat / '.($mode==='create' ? 'Tambah' : 'Edit'))
@section('content')
<div class="max-w-[640px]">
  <a href="{{ route('doors.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-500 hover:text-fq-600 transition">← Kembali ke daftar pintu</a>
  <h2 class="mt-3 font-serif text-[26px] leading-none tracking-tight">{{ $mode==='create' ? 'Tambah' : 'Edit' }} Pintu</h2>
  <p class="mt-1.5 text-sm text-zinc-500">door_code dipakai untuk URL kiosk tablet: <span class="font-mono text-xs bg-zinc-100 px-1.5 py-0.5 rounded border border-zinc-200">/kiosk/{door_code}</span></p>

  <form method="POST" action="{{ $mode==='create' ? route('doors.store') : route('doors.update',$door) }}" class="glass rounded-[24px] p-6 sm:p-7 mt-6 space-y-4">
    @csrf @if($mode==='edit') @method('PUT') @endif
    @if($errors->any())<div class="rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">{{ $errors->first() }}</div>@endif

    <label class="block">
      <span class="text-sm font-bold">door_code <span class="text-fq-600">*</span> <span class="font-normal text-zinc-500">— huruf kecil, angka, strip</span></span>
      <input name="door_code" value="{{ old('door_code',$door->door_code) }}" required pattern="[a-z0-9\-]+" placeholder="front-gate-1" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <label class="block">
      <span class="text-sm font-bold">Nama Pintu <span class="text-fq-600">*</span></span>
      <input name="name" value="{{ old('name',$door->name) }}" required placeholder="Pintu Utama Masuk" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <div class="grid grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm font-bold">Arah</span>
        <select name="direction" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
          <option value="bidirectional" {{ old('direction',$door->direction)=='bidirectional'?'selected':'' }}>Dua arah</option>
          <option value="in" {{ old('direction',$door->direction)=='in'?'selected':'' }}>Masuk saja</option>
          <option value="out" {{ old('direction',$door->direction)=='out'?'selected':'' }}>Keluar saja</option>
        </select>
      </label>
      <label class="block">
        <span class="text-sm font-bold">Relay Channel</span>
        <input type="number" name="relay_channel" value="{{ old('relay_channel',$door->relay_channel ?? 1) }}" min="1" max="16" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
      </label>
    </div>
    <label class="block">
      <span class="text-sm font-bold">Durasi Buka (ms)</span>
      <input type="number" name="pulse_duration_ms" value="{{ old('pulse_duration_ms',$door->pulse_duration_ms ?? 3000) }}" min="500" max="15000" step="500" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
      <span class="text-xs text-zinc-500">3000 = 3 detik pintu terbuka (pulse relay)</span>
    </label>
    <label class="flex items-center gap-3 py-1.5 rounded-2xl border border-zinc-200 bg-white px-4 py-3">
      <input type="checkbox" name="is_active" value="1" {{ old('is_active',$door->is_active ?? true) ? 'checked' : '' }} class="h-5 w-5 rounded border-zinc-300 text-fq-600 focus:ring-fq-500">
      <span class="text-sm font-bold">Pintu aktif — bisa menerima scan</span>
    </label>
    <button class="w-full py-3 rounded-full bg-fq-600 text-white font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">{{ $mode==='create' ? 'Simpan Pintu' : 'Perbarui Pintu' }}</button>
  </form>
</div>
@endsection
