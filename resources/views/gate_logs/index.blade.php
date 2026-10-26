@extends('layouts.app')
@section('title','Log Aktivitas Gate')
@section('breadcrumb','Gate Logs')

@section('content')
{{-- Header --}}
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
  <div>
    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-red-50 border border-red-100 text-[11px] font-bold tracking-widest uppercase text-fq-700">
      ● Log Gate Hardware — tbl_userlog
    </div>
    <h1 class="mt-3 font-serif text-[28px] sm:text-[36px] leading-[0.95] tracking-tight">
      Pintu terkendali,<br>
      <span class="font-space font-bold tracking-[-0.02em]">semua akses <span class="text-fq-600">tercatat rapi.</span></span>
    </h1>
    <p class="mt-3 text-sm text-zinc-500 max-w-[62ch]">
      Menampilkan seluruh riwayat rekaman tap gerbang fisik dari <span class="font-semibold text-zinc-700">Wajah (Face), Sidik Jari (Fingerprint), dan Kartu RFID</span> secara realtime.
    </p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    <span id="liveDot" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-emerald-50 border border-emerald-200 text-xs font-bold text-emerald-700">
      <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span> LIVE
    </span>
    <span id="liveInfo" class="text-[11px] font-mono text-zinc-400">menunggu…</span>
    <button id="toggleLive" class="px-3 py-2 rounded-full bg-white border border-zinc-200 text-xs font-bold hover:bg-zinc-50 shadow-sm transition">⏸️ Jeda</button>
    <a href="{{ route('gate-logs.exportExcel', request()->query()) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold hover:bg-emerald-100 transition shadow-sm">
      <span>📊</span> Export Excel
    </a>
  </div>
</div>

{{-- Periode Label --}}
@php
  $periodeLabel = (!empty($startDate) && !empty($endDate)) 
    ? ($startDate === $endDate ? date('d/m/Y', strtotime($startDate)) : date('d/m/Y', strtotime($startDate)).' — '.date('d/m/Y', strtotime($endDate))) 
    : 'Semua Data';
@endphp

{{-- Stats Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
  <div class="glass rounded-2xl p-4">
    <div class="text-[11px] tracking-widest font-bold text-zinc-400 uppercase">Total Tap Masuk/Keluar <span class="font-normal normal-case tracking-normal text-zinc-400">• {{ $periodeLabel }}</span></div>
    <div id="statTotal" class="text-2xl font-space font-bold mt-1 text-zinc-900">{{ number_format($userTotal) }}</div>
  </div>
  <div class="glass rounded-2xl p-4 border-sky-200/60">
    <div class="text-[11px] tracking-widest font-bold text-sky-600 uppercase">Arah Masuk (IN)</div>
    <div id="statIn" class="text-2xl font-space font-bold mt-1 text-sky-700">{{ number_format($userIn) }}</div>
  </div>
  <div class="glass rounded-2xl p-4 border-amber-200/60">
    <div class="text-[11px] tracking-widest font-bold text-amber-600 uppercase">Arah Keluar (OUT)</div>
    <div id="statOut" class="text-2xl font-space font-bold mt-1 text-amber-700">{{ number_format($userOut) }}</div>
  </div>
</div>

{{-- Filter Form --}}
<div class="glass rounded-[20px] p-4 mb-5">
  <form method="GET" class="flex flex-wrap items-end gap-3">
    <label>
      <span class="text-[11px] font-bold tracking-widest uppercase text-zinc-500">Tgl Awal</span>
      <input type="date" name="start_date" value="{{ $startDate }}" class="mt-1 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
    </label>
    <label>
      <span class="text-[11px] font-bold tracking-widest uppercase text-zinc-500">Tgl Akhir</span>
      <input type="date" name="end_date" value="{{ $endDate }}" class="mt-1 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
    </label>
    <label class="flex-1 min-w-[200px]">
      <span class="text-[11px] font-bold tracking-widest uppercase text-zinc-500">Cari SN / Nama / User ID / Kartu</span>
      <input name="q" value="{{ request('q') }}" placeholder="AYUH... / Fariz / 124 / 104902..." class="mt-1 w-full rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
    </label>
    <label>
      <span class="text-[11px] font-bold tracking-widest uppercase text-zinc-500">Arah (Stat)</span>
      <select name="stat" class="mt-1 rounded-xl border border-zinc-200 bg-white px-3 py-2.5 text-sm">
        <option value="">Semua Arah</option>
        <option value="0" @selected(request('stat')==='0')>IN (Masuk)</option>
        <option value="1" @selected(request('stat')==='1')>OUT (Keluar)</option>
      </select>
    </label>
    <button class="px-5 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold hover:bg-fq-700 transition shadow-sm">Filter</button>
    <a href="{{ route('gate-logs.index') }}" class="px-4 py-2.5 rounded-full bg-white border border-zinc-200 text-sm font-semibold hover:bg-zinc-50 transition">Reset</a>
  </form>
  <div class="mt-2 text-[11px] text-zinc-400">Default: menampilkan <span class="font-bold text-zinc-600">hari ini saja</span> ({{ $periodeLabel }}). Ubah tanggal untuk melihat riwayat sebelumnya.</div>
</div>

{{-- Data Table --}}
<div class="glass rounded-[24px] overflow-hidden shadow-sm">
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-zinc-50/80 border-b border-zinc-100">
        <tr class="text-[11px] tracking-widest font-bold text-zinc-400 uppercase">
          <th class="text-left px-4 py-3"># ID</th>
          <th class="text-left px-3 py-3">Waktu Event</th>
          <th class="text-left px-3 py-3">Metode Scan</th>
          <th class="text-left px-3 py-3">Device Gate</th>
          <th class="text-left px-3 py-3">Pengguna</th>
          <th class="text-center px-3 py-3">No. Kartu</th>
          <th class="text-center px-3 py-3">Arah</th>
          <th class="text-center px-3 py-3">Hasil / Akses</th>
          <th class="text-center px-3 py-3">Detail</th>
        </tr>
      </thead>
      <tbody id="logBody" class="divide-y divide-zinc-100">
        @forelse($logs as $l)
        <tr class="hover:bg-red-50/30 transition" data-log-uid="{{ $l->uid }}">
          {{-- ID --}}
          <td class="px-4 py-3 font-mono text-xs text-zinc-500 whitespace-nowrap">
            #{{ $l->raw_id }}
          </td>

          {{-- Waktu --}}
          <td class="px-3 py-3 whitespace-nowrap text-xs font-medium text-zinc-700">
            {{ $l->time ? $l->time->format('d/m/Y H:i:s') : '—' }}
          </td>

          {{-- Metode --}}
          <td class="px-3 py-3">
            @php $m = strtolower((string)$l->method); @endphp
            @if($m === 'face')
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-50 border border-purple-200 text-purple-700 text-xs font-bold">
                👤 Face
              </span>
            @elseif($m === 'finger')
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold">
                👆 Finger
              </span>
            @else
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold">
                🪪 RFID Card
              </span>
            @endif
          </td>

          {{-- Device --}}
          <td class="px-3 py-3">
            <div class="font-semibold text-zinc-800 leading-none">{{ $l->device_name }}</div>
            <div class="text-[11px] font-mono text-zinc-400 mt-1">{{ $l->sn }}</div>
          </td>

          {{-- Pengguna --}}
          <td class="px-3 py-3">
            <div class="font-bold text-zinc-800 leading-tight">{{ $l->user_name }}</div>
            <div class="text-[11px] font-mono text-zinc-400">
              User ID: <span class="font-semibold text-zinc-600">{{ $l->user_id }}</span>
            </div>
          </td>

          {{-- No Kartu --}}
          <td class="px-3 py-3 text-center font-mono text-xs text-zinc-600 whitespace-nowrap">
            {{ $l->card_no ?: '—' }}
          </td>

          {{-- Arah --}}
          <td class="px-3 py-3 text-center">
            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ (int)$l->stat===1 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-sky-50 text-sky-700 border-sky-200' }}">
              {{ $l->stat_label }}
            </span>
          </td>

          {{-- Akses --}}
          <td class="px-3 py-3 text-center whitespace-nowrap">
            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">
              ✓ SILAKAN MASUK
            </span>
          </td>

          {{-- Detail / Action --}}
          <td class="px-3 py-3 text-center">
            @if(!empty($l->img) && file_exists(public_path($l->img)))
              <a href="{{ asset($l->img) }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-zinc-100 hover:bg-zinc-200 text-xs font-medium text-zinc-700 transition">
                📷 Foto
              </a>
            @else
              <span class="text-xs text-zinc-400 font-mono">Hardware</span>
            @endif
          </td>
        </tr>
        @empty
        <tr id="emptyRow">
          <td colspan="9" class="px-6 py-12 text-center text-sm text-zinc-400">
            Belum ada log rekaman pada periode ini — coba ubah filter tanggal atau kata kunci pencarian.
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="border-t border-zinc-100 px-4 py-3 flex items-center justify-between">
    <div class="text-xs text-zinc-500">
      Halaman {{ $logs->currentPage() }} dari {{ $logs->lastPage() }} • Menampilkan {{ $logs->total() }} log • {{ $periodeLabel }}
    </div>
    <div>{{ $logs->links() }}</div>
  </div>
</div>

{{-- Toast new data --}}
<div id="newToast" class="hidden fixed bottom-6 left-1/2 -translate-x-1/2 z-50 px-4 py-2 rounded-full bg-zinc-900 text-white text-sm font-semibold shadow-lg"></div>

@push('scripts')
<script>
let lastUserId = {{ (int) ($lastUserlogId ?? 0) }};
let live = true;
let timer = null;
const feedUrl = "{{ route('gate-logs.feed') }}";
const bodyEl = document.getElementById('logBody');
const infoEl = document.getElementById('liveInfo');
const dotEl  = document.getElementById('liveDot');
const toastEl = document.getElementById('newToast');
const toggleBtn = document.getElementById('toggleLive');
const statTotalEl = document.getElementById('statTotal');
const statInEl    = document.getElementById('statIn');
const statOutEl   = document.getElementById('statOut');

function showToast(msg){
  toastEl.textContent = msg;
  toastEl.classList.remove('hidden');
  setTimeout(()=> toastEl.classList.add('hidden'), 2500);
}

function rowHtml(l){
  const statCls = l.stat===1 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-sky-50 text-sky-700 border-sky-200';
  const accHtml = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-xs font-bold">✓ SILAKAN MASUK</span>';
  
  const m = (l.method || '').toLowerCase();
  let sourceBadge = '';
  if(m === 'face'){
    sourceBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-purple-50 border border-purple-200 text-purple-700 text-xs font-bold">👤 Face</span>';
  } else if(m === 'finger'){
    sourceBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold">👆 Finger</span>';
  } else {
    sourceBadge = '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-50 border border-blue-200 text-blue-700 text-xs font-bold">🪪 RFID Card</span>';
  }

  const detailHtml = '<span class="text-xs text-zinc-400 font-mono">Hardware</span>';

  return `<tr data-log-uid="${l.uid}" class="hover:bg-red-50/30 transition" style="animation: flash .9s ease">
    <td class="px-4 py-3 font-mono text-xs text-zinc-500 whitespace-nowrap">#${l.id}</td>
    <td class="px-3 py-3 whitespace-nowrap text-xs font-medium text-zinc-700">${l.time ?? ''}</td>
    <td class="px-3 py-3">${sourceBadge}</td>
    <td class="px-3 py-3"><div class="font-semibold text-zinc-800 leading-none">${l.device_name || '—'}</div><div class="text-[11px] font-mono text-zinc-400 mt-1">${l.sn ?? ''}</div></td>
    <td class="px-3 py-3"><div class="font-bold text-zinc-800 leading-tight">${l.user_name ?? ''}</div><div class="text-[11px] font-mono text-zinc-400">User ID: ${l.user_id ?? ''}</div></td>
    <td class="px-3 py-3 text-center font-mono text-xs text-zinc-600 whitespace-nowrap">${l.card_no ?? '—'}</td>
    <td class="px-3 py-3 text-center"><span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border ${statCls}">${l.stat_label}</span></td>
    <td class="px-3 py-3 text-center whitespace-nowrap">${accHtml}</td>
    <td class="px-3 py-3 text-center">${detailHtml}</td>
  </tr>`;
}

async function poll(){
  if(!live) return;
  try{
    const res = await fetch(feedUrl + '?after_user_id=' + lastUserId, { headers: { 'Accept':'application/json' }});
    const j = await res.json();
    if(j.logs && j.logs.length){
      const emptyRow = document.getElementById('emptyRow');
      if(emptyRow) emptyRow.remove();
      j.logs.forEach(l => {
        if(!document.querySelector(`[data-log-uid="${l.uid}"]`)){
          bodyEl.insertAdjacentHTML('afterbegin', rowHtml(l));
        }
      });
      const first = bodyEl.firstElementChild;
      if(first){ first.style.background='#fef2f2'; setTimeout(()=> first.style.background='', 900); }
      lastUserId = j.last_user_id;
      if(j.stats){
        if(statTotalEl) statTotalEl.textContent = Number(j.stats.total).toLocaleString('id-ID');
        if(statInEl)    statInEl.textContent    = Number(j.stats.in).toLocaleString('id-ID');
        if(statOutEl)   statOutEl.textContent   = Number(j.stats.out).toLocaleString('id-ID');
      }
      showToast('+' + j.count + ' aktivitas baru');
      infoEl.textContent = 'baru ' + j.count + ' • ' + new Date().toLocaleTimeString('id-ID');
    } else {
      infoEl.textContent = 'cek ' + new Date().toLocaleTimeString('id-ID') + ' • no new';
    }
  }catch(e){
    infoEl.textContent = 'poll error — cek server';
    console.warn('[gate-logs] poll err', e.message);
  }
}

function startPoll(){
  if(timer) clearInterval(timer);
  timer = setInterval(poll, 2500);
  poll();
}

toggleBtn.addEventListener('click', ()=>{
  live = !live;
  toggleBtn.textContent = live ? '⏸️ Jeda' : '▶️ Lanjut';
  dotEl.classList.toggle('opacity-40', !live);
  infoEl.textContent = live ? 'live lanjut…' : 'dijeda';
  if(live) startPoll();
  else { if(timer) clearInterval(timer); }
});

document.addEventListener('visibilitychange', ()=>{ if(document.visibilityState==='visible' && live) poll(); });
startPoll();
</script>
<style>@keyframes flash{0%{background:#fee2e2}100%{background:transparent}}</style>
@endpush
@endsection
