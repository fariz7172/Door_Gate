@extends('layouts.app')
@section('title', ($mode==='create'?'Tambah':'Edit').' Departemen')
@section('breadcrumb','Master / Departemen / '.($mode==='create'?'Tambah':'Edit'))
@section('content')
<div class="max-w-[560px]">
  <a href="{{ route('departemen.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-500 hover:text-fq-600">← Kembali ke departemen</a>
  <h2 class="mt-3 font-serif text-[26px] leading-none tracking-tight">{{ $mode==='create'?'Tambah':'Edit' }} <span class="font-space font-bold text-fq-600">Departemen</span></h2>
  <p class="mt-1.5 text-sm text-zinc-500">Kode DeptCode <span class="font-mono text-xs bg-zinc-100 px-1.5 py-0.5 rounded border border-zinc-200">number</span> &amp; DeptName.</p>
  <form method="POST" action="{{ $mode==='create'?route('departemen.store'):route('departemen.update',$d) }}" class="glass rounded-[24px] p-6 sm:p-7 mt-6 space-y-4">
    @csrf @if($mode==='edit') @method('PUT') @endif
    @if($errors->any())<div class="rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">{{ $errors->first() }}</div>@endif
    <label class="block">
      <span class="text-sm font-bold">DeptCode (Number)</span>
      <input name="number" value="{{ old('number',$d->number) }}" maxlength="15" placeholder="D01" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      <span class="text-xs text-zinc-400">Maks 15 karakter</span>
    </label>
    <label class="block">
      <span class="text-sm font-bold">Nama Departemen (DeptName)</span>
      <input name="name" value="{{ old('name',$d->name) }}" maxlength="30" placeholder="Trainer & Coach" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      <span class="text-xs text-zinc-400">Maks 30 karakter</span>
    </label>
    <label class="block">
      <span class="text-sm font-bold">Deskripsi</span>
      <input name="description" value="{{ old('description',$d->description) }}" maxlength="100" placeholder="Divisi pelatih fisik gym..." class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
      <span class="text-xs text-zinc-400">Maks 100 karakter</span>
    </label>
    <button class="w-full py-3 rounded-full bg-fq-600 text-white font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">{{ $mode==='create'?'Simpan Departemen':'Perbarui Departemen' }}</button>
  </form>
</div>
@endsection
