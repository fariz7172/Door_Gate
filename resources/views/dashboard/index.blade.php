@extends('layouts.app')
@section('title','Dashboard')
@section('breadcrumb','Ringkasan')

@section('content')
{{-- Hero editorial — asymmetric --}}
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-5 mb-7">
  <div>
    <div class="inline-flex items-center gap-2 glass rounded-full px-3.5 py-1.5 text-xs font-semibold">
      <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.6)]"></span>
      Indonesia Defence System
      <span class="text-zinc-400 font-normal">· Gate Controller Port 7788 &amp; 787</span>
    </div>
    <h2 class="mt-3 font-serif text-[28px] sm:text-[36px] leading-[0.95] tracking-tight">
      Pintu terkendali,<br>
      <span class="font-space font-bold tracking-[-0.02em]">keamanan <span class="text-fq-600">terjaga.</span></span>
    </h2>
    <p class="mt-2 text-sm text-zinc-500 max-w-[52ch] leading-relaxed">Turnstile Gate Hardware Controller — pemantauan biometrik wajah, sidik jari, dan kartu RFID secara real-time dari tabel <span class="font-mono text-xs bg-zinc-100 px-1.5 py-0.5 rounded font-bold text-zinc-800">tbl_userlog</span>.</p>
  </div>
  <div class="flex items-center gap-2 shrink-0">
    <a href="{{ route('gate-logs.index') }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-zinc-900 text-white hover:bg-black text-xs font-bold transition shadow-sm">
      Buka Semua Log Gerbang →
    </a>
  </div>
</div>

{{-- Bento stats --}}
<div class="grid grid-cols-12 gap-4 mb-6">
  {{-- Hero red — spans 6 cols on desktop --}}
  <div class="col-span-12 lg:col-span-6 glass-red rounded-[24px] p-6 text-white relative overflow-hidden flex flex-col justify-between min-h-[168px]">
    <div class="absolute -top-16 -right-16 h-56 w-56 rounded-full bg-white/10 blur-2xl pointer-events-none"></div>
    <div class="absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-black/10 pointer-events-none"></div>
    <div class="relative">
      <div class="text-[11px] tracking-[0.16em] font-bold text-white/70 uppercase">Total Tap Hari Ini</div>
      <div id="statTotal" class="mt-2 font-space font-bold text-[44px] leading-none tracking-[-0.03em]">{{ $stats['total_tap'] }}</div>
      <div class="text-sm text-white/80 font-medium">transaksi tap mesin turnstile hari ini ({{ date('d/m/Y') }})</div>
    </div>
    <div class="relative mt-4 flex items-center gap-2 text-xs font-semibold">
      <span class="px-2.5 py-1 rounded-full bg-white text-fq-700 font-bold">{{ $stats['doors_active'] }} gate online</span>
      <span class="px-2.5 py-1 rounded-full bg-white/15 border border-white/20 text-white">{{ $stats['gates_total'] }} total gerbang</span>
    </div>
  </div>
  {{-- 2 small stats --}}
  <div class="col-span-6 lg:col-span-3 glass rounded-[22px] p-5 flex flex-col justify-between min-h-[168px]">
    <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Arah Masuk (IN)</div>
    <div id="statIn" class="font-space font-bold text-[36px] leading-none tracking-[-0.02em] text-emerald-600">{{ $stats['tap_in'] }}</div>
    <div class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
      <span class="h-6 w-6 rounded-full bg-emerald-100 flex items-center justify-center text-[12px]">↓</span> Tap Masuk (stat: 0)
    </div>
  </div>
  <div class="col-span-6 lg:col-span-3 glass rounded-[22px] p-5 flex flex-col justify-between min-h-[168px]">
    <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Arah Keluar (OUT)</div>
    <div id="statOut" class="font-space font-bold text-[36px] leading-none tracking-[-0.02em] text-blue-600">{{ $stats['tap_out'] }}</div>
    <div class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-700">
      <span class="h-6 w-6 rounded-full bg-blue-100 flex items-center justify-center text-[12px]">↑</span> Tap Keluar (stat: 1)
    </div>
  </div>
</div>

{{-- Pintu Terpasang Grid --}}
<div class="mb-6">
  <div class="flex items-center justify-between gap-3 mb-4">
    <h3 class="font-space font-bold text-[16px] tracking-tight">Pintu Terpasang</h3>
    <a href="{{ route('devicegate.index') }}" class="text-xs font-bold tracking-wide px-3.5 py-1.5 rounded-full bg-zinc-900 text-white hover:bg-black transition">Kelola →</a>
  </div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
    @forelse($deviceGates as $g)
    <div class="group rounded-[18px] border {{ $g->is_connected ? 'bg-white border-red-100 hover:border-red-200 hover:shadow-[0_8px_24px_rgba(220,38,38,.08)]' : 'bg-zinc-50 border-zinc-200 opacity-75' }} p-4 flex flex-col gap-3 transition">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="font-bold text-sm leading-tight truncate">{{ $g->name ?: $g->sn }}</div>
          <div class="text-[11px] font-mono text-zinc-500 truncate">{{ $g->sn }} · {{ $g->ip ?: 'no-ip' }} · <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $g->type_badge }}">{{ $g->type_label }}</span> · {{ $g->stat_label }}</div>
        </div>
        <span class="shrink-0 h-2.5 w-2.5 rounded-full {{ $g->is_connected ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.5)]' : 'bg-red-500' }}"></span>
      </div>
      <div class="flex items-center gap-1.5">
        <span class="text-[10px] font-bold tracking-wide px-2 py-1 rounded-full {{ $g->is_connected ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">{{ $g->is_connected ? 'TERHUBUNG' : 'PUTUS' }}</span>
        <span class="text-[10px] font-semibold px-2 py-1 rounded-full {{ $g->stat===1 ? 'bg-amber-100 text-amber-700 border border-amber-200' : 'bg-emerald-100 text-emerald-700 border border-emerald-200' }} border">{{ $g->stat_label }}</span>
      </div>
      <a href="{{ route('devicegate.index') }}" class="mt-1 inline-flex items-center justify-center gap-1.5 w-full py-2 rounded-full {{ $g->is_connected ? 'bg-fq-600 text-white hover:bg-fq-700' : 'bg-zinc-200 text-zinc-500' }} text-xs font-bold transition">Kelola Device →</a>
    </div>
    @empty
    <div class="col-span-full rounded-2xl border border-dashed border-zinc-200 bg-zinc-50/60 p-8 text-center">
      <div class="font-bold">Belum ada device gate</div>
      <div class="text-sm text-zinc-500 mt-1">Tambahkan device pertama untuk mulai scan.</div>
      <a href="{{ route('devicegate.create') }}" class="inline-block mt-3 px-5 py-2 rounded-full bg-fq-600 text-white text-sm font-bold">+ Tambah Device</a>
    </div>
    @endforelse
  </div>
</div>

{{-- Logs — bespoke table card (TblUserlog) --}}
<div class="glass rounded-[24px] overflow-hidden">
  <div class="px-5 sm:px-6 py-4 flex flex-wrap items-center justify-between gap-3 border-b border-red-100/60">
    <div class="flex items-center gap-3">
      <h3 class="font-space font-bold text-zinc-800">Log Tap Turnstile (tbl_userlog)</h3>
      <span id="liveDot" class="inline-flex items-center gap-1.5 text-[11px] font-bold tracking-wide px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700">● LIVE</span>
    </div>
    <form method="GET" class="flex flex-wrap items-center gap-2">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama / kartu / SN..." class="text-xs rounded-full border border-zinc-200 px-3.5 py-1.5 bg-white focus:border-fq-400 outline-none w-44">
      <select name="stat" class="text-xs rounded-full border border-zinc-200 px-3 py-1.5 bg-white focus:border-fq-400 outline-none">
        <option value="">Semua Arah</option>
        <option value="0" {{ request('stat')==='0' ? 'selected':'' }}>IN (Masuk)</option>
        <option value="1" {{ request('stat')==='1' ? 'selected':'' }}>OUT (Keluar)</option>
      </select>
      <button class="px-4 py-1.5 rounded-full bg-zinc-900 text-white text-xs font-bold hover:bg-black transition">Filter</button>
      @if(request()->hasAny(['q','stat']))
      <a href="{{ route('dashboard') }}" class="px-3 py-1.5 rounded-full bg-zinc-100 text-zinc-600 text-xs font-bold hover:bg-zinc-200 transition">Reset</a>
      @endif
    </form>
  </div>
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-red-50/50 text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">
        <tr>
          <th class="px-4 py-3 text-left whitespace-nowrap">Waktu</th>
          <th class="px-4 py-3 text-left">Perangkat</th>
          <th class="px-4 py-3 text-left">Personel</th>
          <th class="px-4 py-3 text-left">RFID / Kartu</th>
          <th class="px-4 py-3 text-left">Metode</th>
          <th class="px-4 py-3 text-left">Arah</th>
          <th class="px-4 py-3 text-left">Foto Tap</th>
        </tr>
      </thead>
      <tbody id="logBody" class="divide-y divide-zinc-100">
        @forelse($logs as $log)
        <tr class="hover:bg-red-50/30 transition" data-user-id="{{ $log->raw_id }}">
          <td class="px-4 py-3 whitespace-nowrap text-zinc-600 font-mono text-xs">{{ $log->time ? \Carbon\Carbon::parse($log->time)->format('d/m/Y H:i:s') : '—' }}</td>
          <td class="px-4 py-3 font-mono text-xs">
            <div class="font-bold text-zinc-800">{{ $log->device_name }}</div>
            <div class="text-[10px] text-zinc-400">{{ $log->sn }}</div>
          </td>
          <td class="px-4 py-3 text-xs">
            <div class="font-bold text-zinc-900">{{ $log->user_name }}</div>
            <div class="text-[10px] font-mono text-zinc-400">ID: {{ $log->user_id }}</div>
          </td>
          <td class="px-4 py-3 font-mono text-xs text-zinc-700">
            {{ $log->card_no ?: '—' }}
          </td>
          <td class="px-4 py-3">
            @if($log->method === 'face')
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">
                👤 Face
              </span>
            @elseif($log->method === 'finger')
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                👆 Finger
              </span>
            @else
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                💳 RFID Card
              </span>
            @endif
          </td>
          <td class="px-4 py-3">
            @if($log->stat === 1)
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">
                ↑ OUT
              </span>
            @else
              <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                ↓ IN
              </span>
            @endif
          </td>
          <td class="px-4 py-3">
            @if($log->img)
              <a href="{{ asset($log->img) }}" target="_blank" class="inline-block h-8 w-8 rounded-lg overflow-hidden border border-zinc-200 shadow-sm hover:scale-110 transition">
                <img src="{{ asset($log->img) }}" alt="Tap Foto" class="h-full w-full object-cover">
              </a>
            @else
              <span class="text-xs text-zinc-400">—</span>
            @endif
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="7" class="px-4 py-12 text-center">
            <div class="font-bold text-zinc-600">Belum ada aktivitas tap di turnstile hari ini</div>
            <div class="text-xs text-zinc-500 mt-1">Tap kartu atau verifikasi wajah di gate — transaksi akan muncul LIVE di sini.</div>
          </td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="px-5 py-3 border-t border-zinc-100 bg-white/60">{{ $logs->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
let lastUserId = {{ $lastLogId ?? 0 }};

async function pollDashboardGateFeed() {
  try {
    const url = `/gate-logs/feed?after_user_id=${lastUserId}`;
    const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
    if (!res.ok) return;
    const data = await res.json();

    if (data.stats) {
      const elTotal = document.getElementById('statTotal');
      const elIn = document.getElementById('statIn');
      const elOut = document.getElementById('statOut');
      if (elTotal) elTotal.textContent = data.stats.total;
      if (elIn) elIn.textContent = data.stats.in;
      if (elOut) elOut.textContent = data.stats.out;
    }

    if (data.items && data.items.length > 0) {
      const tbody = document.getElementById('logBody');
      const emptyRow = tbody.querySelector('td[colspan="7"]')?.parentElement;
      if (emptyRow) emptyRow.remove();

      for (const item of data.items) {
        if (item.type === 'userlog' && item.id > lastUserId) {
          lastUserId = item.id;
        }

        const tr = document.createElement('tr');
        tr.className = 'hover:bg-red-50/30 transition bg-emerald-50/40 animate-pulse';
        tr.setAttribute('data-user-id', item.id);

        let methodBadge = '';
        if (item.method === 'face') {
          methodBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-purple-50 text-purple-700 border border-purple-200">👤 Face</span>';
        } else if (item.method === 'finger') {
          methodBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">👆 Finger</span>';
        } else {
          methodBadge = '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">💳 RFID Card</span>';
        }

        let statBadge = item.stat === 1
          ? '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-blue-50 text-blue-700 border border-blue-200">↑ OUT</span>'
          : '<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">↓ IN</span>';

        let imgHtml = item.img 
          ? `<a href="${item.img}" target="_blank" class="inline-block h-8 w-8 rounded-lg overflow-hidden border border-zinc-200 shadow-sm hover:scale-110 transition"><img src="${item.img}" alt="Tap Foto" class="h-full w-full object-cover"></a>`
          : '<span class="text-xs text-zinc-400">—</span>';

        tr.innerHTML = `
          <td class="px-4 py-3 whitespace-nowrap text-zinc-600 font-mono text-xs">${item.time}</td>
          <td class="px-4 py-3 font-mono text-xs">
            <div class="font-bold text-zinc-800">${item.device_name}</div>
            <div class="text-[10px] text-zinc-400">${item.sn}</div>
          </td>
          <td class="px-4 py-3 text-xs">
            <div class="font-bold text-zinc-900">${item.user_name}</div>
            <div class="text-[10px] font-mono text-zinc-400">ID: ${item.user_id}</div>
          </td>
          <td class="px-4 py-3 font-mono text-xs text-zinc-700">${item.card_no || '—'}</td>
          <td class="px-4 py-3">${methodBadge}</td>
          <td class="px-4 py-3">${statBadge}</td>
          <td class="px-4 py-3">${imgHtml}</td>
        `;

        tbody.insertBefore(tr, tbody.firstChild);
        setTimeout(() => { tr.classList.remove('bg-emerald-50/40', 'animate-pulse'); }, 2500);
      }

      const rows = tbody.querySelectorAll('tr');
      if (rows.length > 25) {
        for (let i = 25; i < rows.length; i++) rows[i].remove();
      }
    }

    const liveDot = document.getElementById('liveDot');
    if (liveDot) {
      liveDot.textContent = '● LIVE ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }
  } catch(e) {
    console.error('Poll dashboard feed error:', e);
  }
}

setInterval(pollDashboardGateFeed, 3000);
</script>
@endpush
