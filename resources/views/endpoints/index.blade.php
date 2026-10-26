@extends('layouts.app')
@section('title','Device Endpoints')
@section('breadcrumb','Perangkat / Endpoints')

@section('content')
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
  <div>
    <h2 class="font-serif text-[26px] sm:text-[30px] leading-none tracking-tight">Device <span class="font-space font-bold text-fq-600">Endpoints</span></h2>
    <p class="mt-2 text-sm text-zinc-500 max-w-[60ch] leading-relaxed">18 URL hardware — <span class="font-semibold text-zinc-700">Soyal</span> · <span class="font-semibold text-zinc-700">Tasoft Sztimmy</span> · <span class="font-semibold text-zinc-700">Dreampos</span>. Kelola & test tembak langsung ke device.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2 shrink-0">
    @if($stats['total']===0)
      <a href="{{ route('endpoints.seed') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-full bg-emerald-600 text-white text-sm font-bold shadow hover:bg-emerald-700 transition">⬇ Import 18 URL Lama</a>
    @endif
    <a href="{{ route('endpoints.create') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">+ Tambah Endpoint</a>
  </div>
</div>

{{-- Stats bento --}}
<div class="grid grid-cols-12 gap-3 mb-6">
  <div class="col-span-6 lg:col-span-3 glass rounded-[20px] p-4 flex items-center gap-3">
    <div class="h-10 w-10 rounded-xl bg-zinc-900 text-white flex items-center justify-center font-bold text-sm shrink-0">{{ $stats['total'] }}</div>
    <div><div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Total</div><div class="text-sm font-bold leading-none">Endpoint</div></div>
  </div>
  <div class="col-span-6 lg:col-span-3 glass rounded-[20px] p-4 flex items-center gap-3">
    <div class="h-10 w-10 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0">{{ $stats['active'] }}</div>
    <div><div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Aktif</div><div class="text-sm font-bold leading-none">Siap tembak</div></div>
  </div>
  <div class="col-span-6 lg:col-span-3 glass rounded-[20px] p-4 flex items-center gap-3 border-red-200">
    <div class="h-10 w-10 rounded-xl bg-fq-600 text-white flex items-center justify-center font-bold text-[11px] shrink-0">SOYAL</div>
    <div><div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Soyal</div><div class="text-sm font-bold leading-none">{{ $stats['soyal'] }} endpoint</div></div>
  </div>
  <div class="col-span-6 lg:col-span-3 glass rounded-[20px] p-4 flex items-center gap-3">
    <div class="h-10 w-10 rounded-xl bg-amber-500 text-white flex items-center justify-center font-bold text-[11px] shrink-0">TASOFT</div>
    <div><div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Tasoft</div><div class="text-sm font-bold leading-none">{{ $stats['tasoft'] }} endpoint</div></div>
  </div>
</div>

{{-- Filters --}}
<div class="glass rounded-[22px] p-4 mb-4">
  <form method="GET" class="flex flex-wrap items-center gap-2">
    <input name="search" value="{{ request('search') }}" placeholder="Cari URL / deskripsi…" class="flex-1 min-w-[200px] rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
    <select name="group" class="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm">
      <option value="">Semua group</option>
      <option value="soyal" {{ request('group')==='soyal'?'selected':'' }}>Soyal</option>
      <option value="tasoft" {{ request('group')==='tasoft'?'selected':'' }}>Tasoft</option>
      <option value="dreampos" {{ request('group')==='dreampos'?'selected':'' }}>Dreampos</option>
      <option value="device" {{ request('group')==='device'?'selected':'' }}>Device</option>
      <option value="system" {{ request('group')==='system'?'selected':'' }}>System</option>
    </select>
    <select name="status" class="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm">
      <option value="">Semua status</option>
      <option value="active" {{ request('status')==='active'?'selected':'' }}>Aktif</option>
      <option value="inactive" {{ request('status')==='inactive'?'selected':'' }}>Nonaktif</option>
    </select>
    <button class="px-5 py-2 rounded-full bg-zinc-900 text-white text-sm font-bold hover:bg-black">Filter</button>
    @if(request('search')||request('group')||request('status'))<a href="{{ route('endpoints.index') }}" class="text-sm font-semibold text-zinc-500 hover:text-fq-600">Reset</a>@endif
  </form>
</div>

{{-- Table --}}
<div class="glass rounded-[24px] overflow-hidden">
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-red-50/50 text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Group</th>
          <th class="px-4 py-3 text-left">Endpoint</th>
          <th class="px-4 py-3 text-left">Deskripsi</th>
          <th class="px-4 py-3 text-left">Method</th>
          <th class="px-4 py-3 text-left">Status</th>
          <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-100">
        @forelse($endpoints as $ep)
        <tr class="hover:bg-red-50/30 transition">
          <td class="px-4 py-3 font-mono text-xs text-zinc-500">{{ $ep->id }}</td>
          <td class="px-4 py-3">
            @php $gc = ['soyal'=>'bg-fq-600 text-white border-fq-600','tasoft'=>'bg-amber-500 text-white border-amber-500','dreampos'=>'bg-sky-600 text-white border-sky-600','device'=>'bg-zinc-900 text-white','system'=>'bg-zinc-100 text-zinc-700 border-zinc-200']; @endphp
            <span class="inline-flex px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $gc[$ep->group] ?? 'bg-zinc-100 text-zinc-600 border-zinc-200' }}">{{ strtoupper($ep->group) }}</span>
          </td>
          <td class="px-4 py-3 font-mono text-[12px] max-w-[360px] truncate" title="{{ $ep->url }}">{{ $ep->url }}</td>
          <td class="px-4 py-3 text-zinc-600 max-w-[200px] truncate" title="{{ $ep->desc }}">{{ $ep->desc }}</td>
          <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-[11px] font-bold border bg-white border-zinc-200">{{ $ep->http_method }}</span></td>
          <td class="px-4 py-3">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border {{ $ep->is_active ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-zinc-100 text-zinc-500 border-zinc-200' }}">
              <span class="h-2 w-2 rounded-full {{ $ep->is_active ? 'bg-emerald-500' : 'bg-zinc-400' }}"></span>{{ $ep->is_active ? 'AKTIF' : 'OFF' }}
            </span>
          </td>
          <td class="px-4 py-3">
            <div class="flex items-center justify-end gap-1.5">
              <button onclick="openTest({{ $ep->id }}, '{{ addslashes($ep->url) }}', '{{ addslashes($ep->desc) }}')" class="px-3 py-1.5 rounded-full bg-fq-600 text-white text-xs font-bold hover:bg-fq-700 transition">▶ Test</button>
              <a href="{{ route('endpoints.edit',$ep) }}" class="px-3 py-1.5 rounded-full bg-white border border-zinc-200 text-xs font-bold hover:bg-zinc-50">Edit</a>
              <form method="POST" action="{{ route('endpoints.destroy',$ep) }}" onsubmit="return confirm('Hapus endpoint #{{ $ep->id }}?')">@csrf @method('DELETE')<button class="px-2.5 py-1.5 rounded-full bg-red-50 border border-red-200 text-fq-700 text-xs font-bold hover:bg-red-100">Hapus</button></form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="7" class="px-4 py-12 text-center">
          <div class="mx-auto h-12 w-12 rounded-2xl bg-red-50 border border-red-200 flex items-center justify-center text-xl mb-3">◎</div>
          <div class="font-bold">Belum ada endpoint</div>
          <div class="text-xs text-zinc-500 mt-1">Klik tombol di atas untuk import 18 URL lama atau tambah manual.</div>
          <a href="{{ route('endpoints.seed') }}" class="inline-block mt-3 px-4 py-2 rounded-full bg-emerald-600 text-white text-xs font-bold">⬇ Import 18 URL</a>
        </td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="px-5 py-3 border-t border-zinc-100 bg-white/60">{{ $endpoints->links() }}</div>
</div>

{{-- Test drawer --}}
<div id="testDrawer" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" onclick="closeTest()"></div>
  <div class="absolute right-0 top-0 bottom-0 w-full max-w-[520px] bg-white shadow-2xl flex flex-col overflow-hidden">
    <div class="h-[56px] flex items-center justify-between px-5 border-b border-zinc-200 shrink-0">
      <div>
        <div class="font-bold text-sm">Test Endpoint</div>
        <div id="testDesc" class="text-xs text-zinc-500 font-mono truncate max-w-[360px]"></div>
      </div>
      <button onclick="closeTest()" class="h-8 w-8 rounded-full bg-zinc-100 flex items-center justify-center">✕</button>
    </div>
    <div class="flex-1 overflow-auto p-5 space-y-4">
      <div class="rounded-2xl bg-zinc-50 border border-zinc-200 p-3">
        <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">URL</div>
        <div id="testUrl" class="font-mono text-xs break-all mt-1"></div>
      </div>
      <label class="block">
        <span class="text-sm font-bold">Payload JSON (opsional)</span>
        <textarea id="testPayload" rows="6" placeholder='{"user_id": "123", "name": "Test"}' class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-xs font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none"></textarea>
        <span class="text-xs text-zinc-500">Kosongkan untuk pakai default_payload dari DB. Contoh opengate: <span class="font-mono bg-zinc-100 px-1 py-0.5 rounded">{"door":"front-gate-1"}</span></span>
      </label>
      <button onclick="doTest()" id="doTestBtn" class="w-full py-3 rounded-full bg-fq-600 text-white font-bold shadow hover:bg-fq-700 transition">▶ Kirim Request</button>
      <div id="testResult" class="hidden rounded-2xl border p-4 space-y-2">
        <div class="flex items-center gap-2">
          <span id="testStatus" class="px-2.5 py-1 rounded-full text-xs font-bold border"></span>
          <span id="testTime" class="text-xs text-zinc-500"></span>
        </div>
        <pre id="testBody" class="bg-zinc-900 text-zinc-100 rounded-xl p-3 text-xs overflow-auto max-h-[320px] whitespace-pre-wrap break-words"></pre>
      </div>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
let currentId = null;
function openTest(id, url, desc){
  currentId = id;
  document.getElementById('testUrl').textContent = url;
  document.getElementById('testDesc').textContent = desc;
  document.getElementById('testPayload').value = '';
  document.getElementById('testResult').classList.add('hidden');
  document.getElementById('testDrawer').classList.remove('hidden');
  document.body.style.overflow='hidden';
}
function closeTest(){
  document.getElementById('testDrawer').classList.add('hidden');
  document.body.style.overflow='';
}
async function doTest(){
  const btn = document.getElementById('doTestBtn');
  btn.disabled=true; btn.textContent='⏳ Mengirim…';
  const payload = document.getElementById('testPayload').value.trim();
  try{
    const res = await fetch(`/endpoints/${currentId}/test`, {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},
      body: JSON.stringify({payload: payload || null})
    });
    const j = await res.json();
    const box = document.getElementById('testResult');
    box.classList.remove('hidden');
    const statusEl = document.getElementById('testStatus');
    statusEl.textContent = j.ok ? `✓ OK · ${j.status}` : `✕ FAIL · ${j.status || 0}`;
    statusEl.className = `px-2.5 py-1 rounded-full text-xs font-bold border ${j.ok ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'}`;
    document.getElementById('testTime').textContent = (j.elapsed_ms||0)+' ms';
    document.getElementById('testBody').textContent = typeof j.body==='string' ? j.body : JSON.stringify(j.body,null,2);
  }catch(e){
    const box=document.getElementById('testResult'); box.classList.remove('hidden');
    document.getElementById('testBody').textContent = 'Error: '+e.message;
  }finally{btn.disabled=false; btn.textContent='▶ Kirim Request';}
}
</script>
@endpush
