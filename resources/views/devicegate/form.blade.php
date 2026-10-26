@extends('layouts.app')
@section('title', ($mode==='create' ? 'Tambah' : 'Edit').' Device')
@section('breadcrumb','Perangkat / Device / '.($mode==='create' ? 'Tambah' : 'Edit'))
@section('content')
<div class="max-w-[760px]">
  <a href="{{ route('devicegate.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-500 hover:text-fq-600 transition">← Kembali ke daftar device</a>
  <h2 class="mt-3 font-serif text-[26px] leading-none tracking-tight">{{ $mode==='create' ? 'Tambah' : 'Edit' }} <span class="font-space font-bold text-fq-600">Device</span></h2>

  <form method="POST" action="{{ $mode==='create' ? route('devicegate.store') : route('devicegate.update',$d) }}" class="glass rounded-[24px] p-6 sm:p-7 mt-6 space-y-5">
    @csrf @if($mode==='edit') @method('PUT') @endif
    @if($errors->any())<div class="rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">{{ $errors->first() }}</div>@endif

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm font-bold">Nama Device <span class="text-fq-600">*</span></span>
        <input name="name" value="{{ old('name',$d->name) }}" required class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      </label>
      <label class="block">
        <span class="text-sm font-bold">IP Address</span>
        <input name="ip" value="{{ old('ip',$d->ip) }}" placeholder="192.168.1.50" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      </label>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <label class="block">
        <span class="text-[11px] tracking-widest font-bold uppercase text-zinc-500">Tipe Device</span>
        <select name="type" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-bold focus:border-fq-400 outline-none">
          <option value="50" {{ old('type',$d->type)==50?'selected':'' }}>50 - Face</option>
          <option value="1" {{ old('type',$d->type)==1?'selected':'' }}>1 - Soyal</option>
          <option value="0" {{ old('type',$d->type)==0?'selected':'' }}>0 - Finger</option>
        </select>
      </label>
      <label class="block">
        <span class="text-[11px] tracking-widest font-bold uppercase text-zinc-500">Stat (In/Out)</span>
        <select name="stat" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-bold focus:border-fq-400 outline-none">
          <option value="0" {{ old('stat',$d->stat)==0?'selected':'' }}>0 - IN</option>
          <option value="1" {{ old('stat',$d->stat)==1?'selected':'' }}>1 - OUT</option>
        </select>
      </label>
      <label class="block">
        <span class="text-[11px] tracking-widest font-bold uppercase text-zinc-500">Number</span>
        <input name="number" type="number" value="{{ old('number',$d->number) }}" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-center font-mono focus:border-fq-400 outline-none">
      </label>
      <label class="block">
        <span class="text-[11px] tracking-widest font-bold uppercase text-zinc-500">Node ID</span>
        <input name="nodeid" type="number" value="{{ old('nodeid',$d->nodeid) }}" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm text-center font-mono focus:border-fq-400 outline-none">
      </label>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-[11px] tracking-widest font-bold uppercase text-zinc-500">Serial Number (SN)</span>
        <input name="sn" value="{{ old('sn',$d->sn) }}" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 outline-none transition">
      </label>
      <label class="block">
        <span class="text-[11px] tracking-widest font-bold uppercase text-zinc-500">Keterangan <span class="text-fq-600">*</span></span>
        <input name="description" value="{{ old('description',$d->description) }}" required class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 outline-none transition">
      </label>
    </div>

    <div class="hidden">
      <div class="text-[11px] tracking-widest font-bold uppercase text-zinc-500 mb-3">Setting Lift (Opsional)</div>
      <div class="grid grid-cols-4 gap-3">
        @for($i=1;$i<=4;$i++)
        <label class="block text-center">
          <span class="text-[10px] text-zinc-500">Lift {{ $i }}</span>
          <input name="Lift{{ $i }}" type="hidden" value="{{ old('Lift'.$i, $d->{'Lift'.$i} ?? 0) }}">
        </label>
        @endfor
      </div>
    </div>

    <label class="flex items-center gap-3 py-1.5 rounded-2xl border border-zinc-200 bg-white px-4 py-3">
      <input type="hidden" name="flagstatus" value="0">
      <input type="checkbox" name="flagstatus" value="1" {{ old('flagstatus',$d->flagstatus) ? 'checked' : '' }} class="h-5 w-5 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500">
      <div>
        <div class="text-sm font-bold text-emerald-700">Connect</div>
        <div class="text-[11px] text-zinc-500">Centang jika device terhubung jaringan (flagstatus = 1)</div>
      </div>
    </label>

    <button class="w-full py-3 mt-2 rounded-full bg-fq-600 text-white font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">{{ $mode==='create' ? 'Simpan Device' : 'Perbarui Device' }}</button>
  </form>
</div>
@endsection
