@extends('layouts.app')
@section('title','Device Gate')
@section('breadcrumb','Perangkat / Device')

@section('content')
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
  <div>
    <h2 class="font-serif text-[26px] sm:text-[30px] leading-none tracking-tight">Device <span class="font-space font-bold text-fq-600">Gate</span></h2>
    <p class="mt-2 text-sm text-zinc-500 max-w-[60ch] leading-relaxed">Manajemen mesin Face (50), Soyal (1), dan Finger (0). Langsung terhubung dengan API Tasoft/Soyal.</p>
  </div>
  <div class="flex flex-wrap items-center gap-3 shrink-0">
    {{-- Global Enable/Disable All Switch --}}
    <div class="glass px-4 py-2 rounded-full flex items-center gap-3 border border-zinc-200/80 shadow-sm bg-white/80">
      <span class="text-xs font-bold text-zinc-700">Kontrol Semua Device:</span>
      <button type="button"
              id="btnGlobalToggle"
              data-enabled="{{ ($allEnabled ?? false) ? '1' : '0' }}"
              onclick="toggleAllDevices()"
              title="Klik untuk mengaktifkan / menonaktifkan seluruh mesin secara bersamaan"
              class="relative inline-flex h-[26px] w-[50px] shrink-0 cursor-pointer items-center rounded-full p-[2px] transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 {{ ($allEnabled ?? false) ? 'bg-emerald-500' : 'bg-zinc-300' }}">
        <span id="knobGlobalToggle" class="pointer-events-none inline-flex h-[22px] w-[22px] items-center justify-center transform rounded-full bg-white shadow-[0_2px_4px_rgba(0,0,0,0.2)] transition-transform duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] {{ ($allEnabled ?? false) ? 'translate-x-[24px]' : 'translate-x-0' }}">
          <svg id="spinnerGlobalToggle" class="hidden h-3 w-3 animate-spin text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
          </svg>
        </span>
      </button>
      <span id="labelGlobalToggle" class="text-xs font-bold font-mono {{ ($allEnabled ?? false) ? 'text-emerald-600' : 'text-zinc-500' }}">
        {{ ($allEnabled ?? false) ? 'Semua ON' : 'Sebagian/Semua OFF' }}
      </span>
    </div>

    <a href="{{ route('devicegate.create') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">+ Tambah Device</a>
  </div>
</div>

<div class="flex items-center justify-between px-1 mb-2"><span id="autoPingInfo" class="text-[11px] text-zinc-400 font-mono"></span><button onclick="refreshDeviceStatus(true)" class="text-[11px] font-bold text-fq-600 hover:underline">↻ Cek sekarang</button></div>
<div class="glass rounded-[24px] overflow-hidden">
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-red-50/50 text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">
        <tr>
          <th class="px-4 py-3 text-left">Info Device</th>
          <th class="px-4 py-3 text-left">IP / SN</th>
          <th class="px-4 py-3 text-left">Type / Stat</th>
          <th class="px-4 py-3 text-left">Status Mesin</th>
          <th class="px-4 py-3 text-left">Keterangan</th>
          <th class="px-4 py-3 text-right">Aksi API</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-100">
        @forelse($devices as $d)
        <tr data-device-id="{{ $d->id }}" class="hover:bg-red-50/30 transition group">
          <td class="px-4 py-3">
            <div class="flex items-start gap-2">
              <span data-conn-dot class="mt-1 shrink-0 h-2 w-2 rounded-full {{ $d->is_connected ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.6)]' : 'bg-red-500' }}"></span>
              <div>
                <div class="font-bold text-sm text-zinc-800 flex items-center gap-2">{{ $d->name ?: '(No Name)' }} <span data-conn-label class="text-[10px] font-bold px-1.5 py-0.5 rounded-full {{ $d->is_connected ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' }}">{{ $d->is_connected ? 'Terhubung' : 'Putus Jaringan' }}</span></div>
                <div class="text-[11px] text-zinc-500 font-mono mt-0.5">#{{ $d->number }} · Node {{ $d->nodeid }}</div>
              </div>
            </div>
          </td>
          <td class="px-4 py-3 font-mono text-[11px] text-zinc-600">
            <div>{{ $d->ip ?: '-' }}</div>
            <div class="opacity-70 mt-0.5 text-zinc-900 font-bold bg-zinc-100 rounded px-1 inline-block">{{ $d->sn ?: '-' }}</div>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center gap-1.5 mb-1.5">
              <span class="px-2 py-0.5 rounded-md text-[10px] font-bold border {{ $d->type_badge }}">{{ $d->type_label }}</span>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $d->stat===1 ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ $d->stat_label }}</span>
          </td>
          <td class="px-4 py-3 whitespace-nowrap">
            <div class="flex items-center gap-3">
              {{-- Bespoke iOS-style slide switch --}}
              <button type="button"
                      data-toggle-enable="{{ $d->id }}"
                      data-enabled="{{ (int)($d->is_enabled ?? 1) }}"
                      onclick="toggleEnable({{ $d->id }})"
                      title="{{ $d->is_enabled ? 'Klik untuk menonaktifkan pencocokan wajah mesin' : 'Klik untuk mengaktifkan kembali mesin' }}"
                      class="relative inline-flex h-[26px] w-[50px] shrink-0 cursor-pointer items-center rounded-full p-[2px] transition-colors duration-300 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:ring-offset-2 {{ $d->is_enabled ? 'bg-emerald-500' : 'bg-zinc-300' }}">
                <span data-knob class="pointer-events-none inline-flex h-[22px] w-[22px] items-center justify-center transform rounded-full bg-white shadow-[0_2px_4px_rgba(0,0,0,0.2)] transition-transform duration-300 ease-[cubic-bezier(0.34,1.56,0.64,1)] {{ $d->is_enabled ? 'translate-x-[24px]' : 'translate-x-0' }}">
                  <svg data-spinner class="hidden h-3 w-3 animate-spin text-zinc-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                  </svg>
                </span>
              </button>

              <span data-enable-badge class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border transition-colors {{ $d->is_enabled ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-100 text-zinc-600 border-zinc-200' }}">
                <span data-enable-dot class="w-1.5 h-1.5 rounded-full {{ $d->is_enabled ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-400' }}"></span>
                <span data-enable-text>{{ $d->is_enabled ? 'Diaktifkan' : 'Dinonaktifkan' }}</span>
              </span>
            </div>
            <!-- @if(! $d->is_connected)
              <div class="text-[10px] text-amber-600 font-bold mt-1 flex items-center gap-1">
                <span>⚠</span> <span>Mesin offline</span>
              </div>
            @endif -->
          </td>
          <td class="px-4 py-3 text-[11px] text-zinc-500 max-w-[160px] truncate" title="{{ $d->description }}">{{ $d->description ?: '-' }}</td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1.5">
              <button data-action="open" title="Open Gate" onclick="hitAction({{ $d->id }}, 'open')" class="h-8 w-8 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 hover:bg-emerald-100 flex items-center justify-center transition shadow-sm">🔓</button>
              <button title="Clean Log" onclick="hitAction({{ $d->id }}, 'cleanlogs')" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-zinc-600 hover:bg-zinc-50 flex items-center justify-center transition">🧹</button>
              <button title="Cek Koneksi / Get Time" onclick="hitAction({{ $d->id }}, 'gettime')" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-sky-600 hover:bg-sky-50 border-sky-100 flex items-center justify-center transition shadow-sm">📡</button>
              <button title="Set Time" onclick="openTimeModal({{ $d->id }}, '{{ $d->sn }}')" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-amber-600 hover:bg-amber-50 border-amber-100 flex items-center justify-center transition shadow-sm">🕒</button>
              <button title="Reboot" onclick="if(confirm('Reboot device ini?')) hitAction({{ $d->id }}, 'reboot')" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-red-600 hover:bg-red-50 border-red-100 flex items-center justify-center transition shadow-sm">🔄</button>
              <a title="Edit Device" href="{{ route('devicegate.edit',$d) }}" class="h-8 w-8 rounded-xl bg-zinc-900 text-white hover:bg-black flex items-center justify-center transition ml-1">✏️</a>
              <form method="POST" action="{{ route('devicegate.destroy',$d) }}" onsubmit="return confirm('Hapus device {{ $d->name }} (SN: {{ $d->sn }})?')" class="inline">
                @csrf @method('DELETE')
                <button title="Hapus Device" class="h-8 w-8 rounded-xl bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 flex items-center justify-center transition">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="6" class="px-4 py-12 text-center">Belum ada device</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="px-5 py-3 border-t border-zinc-100 bg-white/60">{{ $devices->links() }}</div>
</div>

{{-- Toast Notifikasi Elegan (Tengah Atas) --}}
<div id="deviceToast" class="fixed top-6 left-1/2 -translate-x-1/2 -translate-y-[150%] opacity-0 z-[9999] flex items-center justify-center gap-3 px-6 py-4 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.2)] border text-[15px] font-bold transition-all duration-400 ease-[cubic-bezier(0.175,0.885,0.32,1.275)] min-w-[320px] max-w-[90vw] pointer-events-none">
  <span id="deviceToastIcon" class="text-2xl leading-none"></span>
  <span id="deviceToastMsg" class="leading-snug text-center"></span>
</div>

{{-- Modal Set Time --}}
<div id="timeModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" onclick="closeTime()"></div>
  <div class="relative glass rounded-[24px] p-6 w-full max-w-sm shadow-2xl scale-95 opacity-0 transition-all duration-200" id="timeBox">
    <div class="flex items-start justify-between mb-4">
      <div>
        <div class="font-bold text-lg font-space tracking-tight">Set Time</div>
        <div class="text-[11px] text-zinc-500 font-mono mt-0.5" id="timeSn"></div>
      </div>
      <button onclick="closeTime()" class="h-8 w-8 rounded-full bg-zinc-100 hover:bg-zinc-200 flex items-center justify-center text-zinc-500 transition">✕</button>
    </div>
    <label class="block mb-4">
      <span class="text-xs font-bold uppercase tracking-widest text-zinc-500">Waktu (Y-m-d H:i:s)</span>
      <input id="timeInput" type="text" placeholder="2025-10-22 09:59:05" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
    </label>
    <button id="btnSetTime" class="w-full py-3 rounded-full bg-fq-600 text-white font-bold shadow hover:bg-fq-700 transition">Kirim Set Time</button>
  </div>
</div>
@endsection

@push('scripts')
<script>
let pingTimer = null;

async function refreshDeviceStatus(showLog = false){
  try {
    const res = await fetch(`{{ route('devicegate.pingAll') }}`, { headers: { 'Accept': 'application/json' } });
    const j = await res.json();
    if(j.devices){
      Object.values(j.devices).forEach(d => {
        const row = document.querySelector(`tr[data-device-id="${d.id}"]`);
        if(!row) return;
        const dot = row.querySelector('[data-conn-dot]');
        const label = row.querySelector('[data-conn-label]');
        if(dot){
          dot.className = 'mt-1 shrink-0 h-2 w-2 rounded-full ' + (d.is_connected ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.6)]' : 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,.5)]');
        }
        if(label){
          label.textContent = d.is_connected ? 'Terhubung' : 'Putus Jaringan';
          label.className = 'text-[10px] font-bold px-1.5 py-0.5 rounded-full ' + (d.is_connected ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
        }
        const btnOpen = row.querySelector('[data-action="open"]');
        if(btnOpen){
          if(!d.is_connected){
            btnOpen.classList.add('opacity-40','pointer-events-none');
            btnOpen.title = 'Offline — Putus Jaringan';
          } else {
            btnOpen.classList.remove('opacity-40','pointer-events-none');
            btnOpen.title = 'Open Gate';
          }
        }
      });
      if(showLog) console.log(`[DeviceGate] Auto ping ${j.checked_count} device selesai`, j.devices);
      const el = document.getElementById('autoPingInfo');
      if(el) el.textContent = '↻ auto cek ' + new Date().toLocaleTimeString('id-ID') + ' · ' + j.checked_count + ' device';
    }
  } catch(e){
    console.warn('[DeviceGate] Auto ping gagal — pastikan gate:serve aktif di port 787', e.message);
    const el = document.getElementById('autoPingInfo');
    if(el) el.textContent = '⚠️ gate:serve tidak terhubung (port 787)';
  }
}

// Cek saat halaman dibuka + polling tiap 15 detik agar Putus Jaringan update otomatis
document.addEventListener('DOMContentLoaded', () => {
  refreshDeviceStatus(true);
  if(pingTimer) clearInterval(pingTimer);
  pingTimer = setInterval(() => refreshDeviceStatus(false), 15000);
});
document.addEventListener('visibilitychange', () => {
  if(document.visibilityState === 'visible') refreshDeviceStatus(false);
});

// 25 Sept 26 — slide switch enable/disable mesin (setdevinfo disable_match)
async function toggleEnable(id){
  const btn = document.querySelector(`[data-toggle-enable="${id}"]`);
  if(!btn || btn.dataset.busy === '1') return;
  
  const knob = btn.querySelector('[data-knob]');
  const spinner = btn.querySelector('[data-spinner]');
  const cell = btn.closest('td');
  const badge = cell ? cell.querySelector('[data-enable-badge]') : null;
  const dot = cell ? cell.querySelector('[data-enable-dot]') : null;
  const text = cell ? cell.querySelector('[data-enable-text]') : null;

  btn.dataset.busy = '1';
  btn.classList.add('opacity-70', 'cursor-wait');
  if(spinner) spinner.classList.remove('hidden');

  const cur = btn.dataset.enabled === '1';
  const action = cur ? 'disable' : 'enable';
  const actName = cur ? 'Nonaktifkan Mesin' : 'Aktifkan Mesin';

  try {
    const res = await fetch(`/devicegate/${id}/${action}`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    });
    const j = await res.json();

    if(j.ok){
      const on = j.is_enabled === 1;
      btn.dataset.enabled = on ? '1' : '0';
      
      // Update background track & knob position
      if(on){
        btn.classList.remove('bg-zinc-300');
        btn.classList.add('bg-emerald-500');
        knob.classList.remove('translate-x-0');
        knob.classList.add('translate-x-[24px]');
      } else {
        btn.classList.remove('bg-emerald-500');
        btn.classList.add('bg-zinc-300');
        knob.classList.remove('translate-x-[24px]');
        knob.classList.add('translate-x-0');
      }

      // Update badge label
      if(badge){
        badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border transition-colors ' + 
          (on ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-100 text-zinc-600 border-zinc-200');
      }
      if(dot){
        dot.className = 'w-1.5 h-1.5 rounded-full ' + (on ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-400');
      }
      if(text){
        text.textContent = on ? 'Diaktifkan' : 'Dinonaktifkan';
      }

      btn.title = on ? 'Klik untuk menonaktifkan pencocokan wajah mesin' : 'Klik untuk mengaktifkan kembali mesin';
      showDeviceToast(on ? '✓ Mesin berhasil diaktifkan' : '✓ Mesin berhasil dinonaktifkan', true);
    } else {
      const reason = j.msg || 'Perintah gagal terkirim ke mesin';
      showDeviceToast(`✕ Gagal: ${reason}`, false);
    }
  } catch(e){
    showDeviceToast(`✕ Error ${actName}: ${e.message}`, false);
  } finally {
    delete btn.dataset.busy;
    btn.classList.remove('opacity-70', 'cursor-wait');
    if(spinner) spinner.classList.add('hidden');
  }
}

async function toggleAllDevices(){
  const btn = document.getElementById('btnGlobalToggle');
  if(!btn || btn.dataset.busy === '1') return;

  const cur = btn.dataset.enabled === '1';
  const targetState = !cur;
  const targetInt = targetState ? 1 : 0;

  if(!confirm(`Apakah Anda yakin ingin ${targetState ? 'MENGAKTIFKAN' : 'MENONAKTIFKAN'} seluruh mesin secara bersamaan?`)) {
    return;
  }

  btn.dataset.busy = '1';
  btn.classList.add('opacity-70', 'cursor-wait');
  const spinner = document.getElementById('spinnerGlobalToggle');
  const knob = document.getElementById('knobGlobalToggle');
  const label = document.getElementById('labelGlobalToggle');
  if(spinner) spinner.classList.remove('hidden');

  try {
    const res = await fetch(`{{ route('devicegate.toggleAll') }}`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': '{{ csrf_token() }}',
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify({ is_enabled: targetInt })
    });
    const j = await res.json();

    if(j.ok){
      btn.dataset.enabled = targetInt ? '1' : '0';
      if(targetState){
        btn.classList.remove('bg-zinc-300');
        btn.classList.add('bg-emerald-500');
        if(knob){ knob.classList.remove('translate-x-0'); knob.classList.add('translate-x-[24px]'); }
        if(label){ label.textContent = 'Semua ON'; label.className = 'text-xs font-bold font-mono text-emerald-600'; }
      } else {
        btn.classList.remove('bg-emerald-500');
        btn.classList.add('bg-zinc-300');
        if(knob){ knob.classList.remove('translate-x-[24px]'); knob.classList.add('translate-x-0'); }
        if(label){ label.textContent = 'Semua OFF'; label.className = 'text-xs font-bold font-mono text-zinc-500'; }
      }

      // Update all individual device switches on table page
      document.querySelectorAll('[data-toggle-enable]').forEach(rowBtn => {
        rowBtn.dataset.enabled = targetInt ? '1' : '0';
        const rowKnob = rowBtn.querySelector('[data-knob]');
        const cell = rowBtn.closest('td');
        const badge = cell ? cell.querySelector('[data-enable-badge]') : null;
        const dot = cell ? cell.querySelector('[data-enable-dot]') : null;
        const text = cell ? cell.querySelector('[data-enable-text]') : null;

        if(targetState){
          rowBtn.classList.remove('bg-zinc-300');
          rowBtn.classList.add('bg-emerald-500');
          if(rowKnob){ rowKnob.classList.remove('translate-x-0'); rowKnob.classList.add('translate-x-[24px]'); }
        } else {
          rowBtn.classList.remove('bg-emerald-500');
          rowBtn.classList.add('bg-zinc-300');
          if(rowKnob){ rowKnob.classList.remove('translate-x-[24px]'); rowKnob.classList.add('translate-x-0'); }
        }

        if(badge){
          badge.className = 'inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border transition-colors ' + 
            (targetState ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-100 text-zinc-600 border-zinc-200');
        }
        if(dot){
          dot.className = 'w-1.5 h-1.5 rounded-full ' + (targetState ? 'bg-emerald-500 animate-pulse' : 'bg-zinc-400');
        }
        if(text){
          text.textContent = targetState ? 'Diaktifkan' : 'Dinonaktifkan';
        }
      });

      showDeviceToast(`✨ ${j.msg}`, true);
    } else {
      showDeviceToast(`✕ Gagal: ${j.msg || 'Gagal memperbarui status seluruh device'}`, false);
    }
  } catch(e){
    showDeviceToast(`✕ Error global toggle: ${e.message}`, false);
  } finally {
    delete btn.dataset.busy;
    btn.classList.remove('opacity-70', 'cursor-wait');
    if(spinner) spinner.classList.add('hidden');
  }
}

async function hitAction(id, action){
  const btn = event.currentTarget;
  const ori = btn.innerHTML;
  btn.innerHTML = '⏳'; btn.disabled = true;
  
  const actionNames = {
    'open': 'Open Gate',
    'cleanlogs': 'Clean Log',
    'gettime': 'Cek Koneksi (Get Time)',
    'reboot': 'Reboot'
  };
  const actName = actionNames[action] || action;

  try {
    const res = await fetch(`/devicegate/${id}/${action}`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
    });
    const j = await res.json();
    
    if(j.ok) {
      if(action === 'gettime' && !j.is_connected) {
         showDeviceToast(`✕ Gagal: ${actName} (Mesin Offline/Putus)`, false);
      } else {
         showDeviceToast(`✓ Berhasil: Perintah ${actName} terkirim!`, true);
      }
      refreshDeviceStatus(false);
    } else {
      let err = (j.body && (j.body.error || j.body.message)) || JSON.stringify(j.body);
      showDeviceToast(`✕ Gagal ${actName}: ${err}`, false);
    }
  } catch(e) {
    showDeviceToast(`✕ Error ${actName}: ${e.message}`, false);
  } finally {
    btn.innerHTML = ori; btn.disabled = false;
  }
}

let toastTimer = null;
function showDeviceToast(msg, ok = true) {
  const toast = document.getElementById('deviceToast');
  if(!toast) return;
  const icon = document.getElementById('deviceToastIcon');
  const text = document.getElementById('deviceToastMsg');
  
  clearTimeout(toastTimer);
  icon.textContent = ok ? '✨' : '⚠️';
  text.textContent = msg;
  
  // Background & border color based on status
  const themeClasses = ok 
    ? 'bg-emerald-600 text-white border-emerald-500 shadow-emerald-500/30' 
    : 'bg-red-600 text-white border-red-500 shadow-red-500/30';
  
  toast.className = `fixed top-6 left-1/2 -translate-x-1/2 translate-y-0 opacity-100 z-[9999] flex items-center justify-center gap-3 px-6 py-4 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.3)] border text-[15px] font-bold transition-all duration-400 ease-[cubic-bezier(0.175,0.885,0.32,1.275)] min-w-[320px] max-w-[90vw] pointer-events-auto ${themeClasses}`;
  
  toastTimer = setTimeout(() => {
    toast.className = `fixed top-6 left-1/2 -translate-x-1/2 -translate-y-[150%] opacity-0 z-[9999] flex items-center justify-center gap-3 px-6 py-4 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.2)] border text-[15px] font-bold transition-all duration-400 ease-[cubic-bezier(0.175,0.885,0.32,1.275)] min-w-[320px] max-w-[90vw] pointer-events-none ${themeClasses}`;
  }, 4000);
}

// Hapus showRes dan closeRes karena tidak dipakai lagi
// Modal Set Time
let curTimeId = null;
function openTimeModal(id, sn){
  curTimeId = id;
  document.getElementById('timeSn').textContent = 'SN: ' + sn;
  const now = new Date();
  // format Y-m-d H:i:s
  const p = (n) => n.toString().padStart(2,'0');
  document.getElementById('timeInput').value = `${now.getFullYear()}-${p(now.getMonth()+1)}-${p(now.getDate())} ${p(now.getHours())}:${p(now.getMinutes())}:${p(now.getSeconds())}`;
  
  document.getElementById('timeModal').classList.remove('hidden');
  setTimeout(()=> document.getElementById('timeBox').classList.remove('scale-95','opacity-0'), 10);
}
function closeTime(){
  document.getElementById('timeBox').classList.add('scale-95','opacity-0');
  setTimeout(()=> document.getElementById('timeModal').classList.add('hidden'), 200);
}
document.getElementById('btnSetTime').addEventListener('click', async function(){
  const btn = this;
  btn.disabled = true; btn.textContent = 'Mengirim...';
  const val = document.getElementById('timeInput').value;
  try {
    const res = await fetch(`/devicegate/${curTimeId}/settime`, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ Time: val })
    });
    const j = await res.json();
    closeTime();
    
    if(j.ok) {
       showDeviceToast('✓ Berhasil: Waktu berhasil disinkronkan ke mesin!', true);
    } else {
       let err = (j.body && (j.body.error || j.body.message)) || JSON.stringify(j.body);
       showDeviceToast(`✕ Gagal Set Time: ${err}`, false);
    }
  } catch(e) {
    showDeviceToast(`✕ Error Set Time: ${e.message}`, false);
  } finally {
    btn.disabled = false; btn.textContent = 'Kirim Set Time';
  }
});
</script>
@endpush
