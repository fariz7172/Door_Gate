@extends('layouts.app')
@section('title','Pintu & Relay')
@section('breadcrumb','Perangkat')

@section('content')
<div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
  <div>
    <h2 class="font-serif text-[26px] sm:text-[30px] leading-none tracking-tight">Pintu & <span class="font-space font-bold text-fq-600">Relay</span></h2>
    <p class="mt-2 text-sm text-zinc-500 max-w-[48ch] leading-relaxed">Tiap pintu = 1 kiosk fullscreen di tablet. <span class="font-mono text-xs bg-zinc-100 px-1.5 py-0.5 rounded">/kiosk/{door_code}</span> — arah, channel relay, & durasi pulse.</p>
  </div>
  <a href="{{ route('doors.create') }}" class="inline-flex items-center justify-center gap-1.5 px-5 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition shrink-0">+ Tambah Pintu</a>
</div>

<div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
@forelse($doors as $d)
  <div class="group glass rounded-[22px] p-5 relative overflow-hidden flex flex-col hover:shadow-[0_12px_32px_rgba(220,38,38,.10)] hover:border-red-200 transition">
    <div class="absolute -top-10 -right-10 h-28 w-28 rounded-full bg-gradient-to-br from-red-100/60 to-transparent opacity-60 pointer-events-none"></div>
    <div class="relative flex items-start justify-between gap-3">
      <div class="min-w-0">
        <div class="font-space font-bold text-[15px] leading-tight truncate">{{ $d->name }}</div>
        <div class="text-[11px] font-mono text-zinc-500 truncate">{{ $d->door_code }}</div>
      </div>
      <span class="shrink-0 text-[10px] font-bold tracking-[0.08em] px-2.5 py-1 rounded-full border {{ $d->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-100 text-zinc-500 border-zinc-200' }}">{{ $d->is_active ? 'AKTIF' : 'OFF' }}</span>
    </div>
    <div class="relative mt-3 flex flex-wrap gap-1.5">
      <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-white border border-zinc-200">{{ $d->direction }}</span>
      <span class="text-[11px] px-2.5 py-1 rounded-full bg-white border border-zinc-200">ch{{ $d->relay_channel }} · {{ $d->pulse_duration_ms }}ms</span>
    </div>
    <div class="relative mt-4 grid grid-cols-[1fr_auto_auto] gap-2">
      <a href="{{ route('kiosk.show',$d->door_code) }}" target="_blank" class="inline-flex items-center justify-center gap-1 px-3 py-2 rounded-full bg-zinc-900 text-white text-xs font-bold hover:bg-black transition">Kiosk ↗</a>
      <a href="{{ route('doors.edit',$d) }}" class="inline-flex items-center justify-center px-3.5 py-2 rounded-full bg-white border border-zinc-200 text-xs font-bold hover:bg-zinc-50 transition">Edit</a>
      <form method="POST" action="{{ route('doors.destroy',$d) }}" onsubmit="return confirm('Hapus {{ $d->door_code }}?')">@csrf @method('DELETE')<button class="h-full px-3 py-2 rounded-full bg-red-50 border border-red-200 text-fq-700 text-xs font-bold hover:bg-red-100 transition">Hapus</button></form>
    </div>
  </div>
@empty
  <div class="col-span-full glass rounded-[24px] p-10 text-center border-dashed">
    <div class="mx-auto h-12 w-12 rounded-2xl bg-red-50 border border-red-200 flex items-center justify-center text-xl">⬢</div>
    <div class="mt-3 font-bold">Belum ada pintu</div>
    <div class="text-sm text-zinc-500 mt-1">Tambahkan pintu pertama — nanti kiosk otomatis muncul di sidebar.</div>
    <a href="{{ route('doors.create') }}" class="inline-block mt-4 px-5 py-2 rounded-full bg-fq-600 text-white text-sm font-bold hover:bg-fq-700">+ Tambah Pintu</a>
  </div>
@endforelse
</div>
@endsection
