@extends('layouts.app')
@section('title', ($mode==='create' ? 'Tambah' : 'Edit').' Endpoint')
@section('breadcrumb','Perangkat / Endpoints / '.($mode==='create' ? 'Tambah' : 'Edit'))
@section('content')
<div class="max-w-[640px]">
  <a href="{{ route('endpoints.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-zinc-500 hover:text-fq-600">← Kembali ke endpoints</a>
  <h2 class="mt-3 font-serif text-[26px] leading-none tracking-tight">{{ $mode==='create' ? 'Tambah' : 'Edit' }} Endpoint Device</h2>
  <p class="mt-1.5 text-sm text-zinc-500">URL hardware — Soyal / Tasoft / Dreampos. Dipakai untuk <span class="font-mono text-xs bg-zinc-100 px-1 py-0.5 rounded border border-zinc-200">DeviceGatewayService::call()</span></p>

  <form method="POST" action="{{ $mode==='create' ? route('endpoints.store') : route('endpoints.update',$ep) }}" class="glass rounded-[24px] p-6 sm:p-7 mt-6 space-y-4">
    @csrf @if($mode==='edit') @method('PUT') @endif
    @if($errors->any())<div class="rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium">{{ $errors->first() }}</div>@endif

    <label class="block">
      <span class="text-sm font-bold">URL Endpoint <span class="text-fq-600">*</span></span>
      <input name="url" value="{{ old('url',$ep->url) }}" required placeholder="http://localhost:787/sztimmy/opengate" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <label class="block">
      <span class="text-sm font-bold">Deskripsi <span class="text-fq-600">*</span></span>
      <input name="desc" value="{{ old('desc',$ep->desc) }}" required placeholder="Opengate" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <div class="grid grid-cols-2 gap-4">
      <label class="block">
        <span class="text-sm font-bold">Group</span>
        <select name="group" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm">
          <option value="soyal" {{ old('group',$ep->group)=='soyal'?'selected':'' }}>Soyal</option>
          <option value="tasoft" {{ old('group',$ep->group)=='tasoft'?'selected':'' }}>Tasoft (Sztimmy)</option>
          <option value="dreampos" {{ old('group',$ep->group)=='dreampos'?'selected':'' }}>Dreampos</option>
          <option value="device" {{ old('group',$ep->group)=='device'?'selected':'' }}>Device</option>
          <option value="system" {{ old('group',$ep->group)=='system'?'selected':'' }}>System</option>
          <option value="general" {{ old('group',$ep->group)=='general'?'selected':'' }}>General</option>
        </select>
      </label>
      <label class="block">
        <span class="text-sm font-bold">HTTP Method</span>
        <select name="http_method" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm">
          <option value="POST" {{ old('http_method',$ep->http_method)=='POST'?'selected':'' }}>POST</option>
          <option value="GET" {{ old('http_method',$ep->http_method)=='GET'?'selected':'' }}>GET</option>
          <option value="PUT" {{ old('http_method',$ep->http_method)=='PUT'?'selected':'' }}>PUT</option>
          <option value="PATCH" {{ old('http_method',$ep->http_method)=='PATCH'?'selected':'' }}>PATCH</option>
          <option value="DELETE" {{ old('http_method',$ep->http_method)=='DELETE'?'selected':'' }}>DELETE</option>
        </select>
      </label>
    </div>
    <label class="block">
      <span class="text-sm font-bold">Default Payload JSON <span class="font-normal text-zinc-500">— opsional</span></span>
      <textarea name="default_payload" rows="3" placeholder='{"key":"value"}' class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-xs font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">{{ old('default_payload',$ep->default_payload) }}</textarea>
      <span class="text-xs text-zinc-500">Akan di-merge dengan payload saat test. Kosongkan jika endpoint tidak butuh body.</span>
    </label>
    <label class="flex items-center gap-3 py-1.5 rounded-2xl border border-zinc-200 bg-white px-4 py-3">
      <input type="checkbox" name="is_active" value="1" {{ old('is_active',$ep->is_active ?? true) ? 'checked' : '' }} class="h-5 w-5 rounded border-zinc-300 text-fq-600 focus:ring-fq-500">
      <span class="text-sm font-bold">Aktif — bisa dipanggil gateway</span>
    </label>
    <button class="w-full py-3 rounded-full bg-fq-600 text-white font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">{{ $mode==='create' ? 'Simpan Endpoint' : 'Perbarui Endpoint' }}</button>
  </form>
</div>
@endsection
