@extends('layouts.app')
@section('title', $mode==='edit' ? 'Edit Group' : 'Tambah Group')
@section('breadcrumb','Master / Device Group')
@section('content')
<div class="max-w-[720px]">
  <div class="glass rounded-[24px] p-6 sm:p-8">
    <h2 class="font-space font-bold text-[18px] tracking-tight">{{ $mode==='edit' ? 'Edit Group' : 'Tambah Group' }}</h2>
    <p class="text-sm text-zinc-500 mt-1">Isi kode group & pilih device yang masuk ke group ini — <span class="font-mono text-xs bg-zinc-100 px-1 py-0.5 rounded">devicegate.groupid</span> akan otomatis ter-update.</p>
    <form method="POST" action="{{ $mode==='edit' ? route('devicegroup.update',$g) : route('devicegroup.store') }}" class="mt-6 space-y-5">
      @csrf @if($mode==='edit') @method('PUT') @endif

      <div class="grid sm:grid-cols-2 gap-4">
        <label class="block">
          <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Number (kode group)</span>
          <input name="number" value="{{ old('number',$g->number) }}" maxlength="11" placeholder="mis. G01" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
          @error('number')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
          <span class="text-[11px] text-zinc-400">Maks 11 karakter</span>
        </label>
        <label class="block">
          <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Nama Group</span>
          <input name="name" value="{{ old('name',$g->name) }}" maxlength="30" placeholder="mis. Lobby Utama" class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
          @error('name')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
          <span class="text-[11px] text-zinc-400">Maks 30 karakter</span>
        </label>
      </div>

      <label class="block">
        <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Deskripsi</span>
        <textarea name="description" rows="3" maxlength="1000" placeholder="Keterangan group..." class="mt-1.5 w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition resize-none">{{ old('description',$g->description) }}</textarea>
        @error('description')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        <span class="text-[11px] text-zinc-400">Maks 1000 karakter</span>
      </label>

      {{-- Device picker --}}
      <div class="rounded-[20px] border border-zinc-200 bg-zinc-50/60 p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
          <div>
            <div class="text-xs font-bold tracking-widest uppercase text-zinc-500">Device Gate <span class="font-normal normal-case tracking-normal text-[11px] text-zinc-400">— centang device untuk masuk group</span></div>
            <div class="text-[11px] text-zinc-400 mt-0.5"><span id="pickCount" class="font-bold text-fq-600">0</span> device terpilih · total {{ $deviceGates->count() }} device</div>
          </div>
          <div class="flex items-center gap-1.5">
            <button type="button" onclick="pickAll(true)" class="px-3 py-1.5 rounded-full bg-zinc-900 text-white text-xs font-bold hover:bg-black transition">Pilih semua</button>
            <button type="button" onclick="pickAll(false)" class="px-3 py-1.5 rounded-full bg-white border border-zinc-200 text-xs font-semibold hover:bg-zinc-50 transition">Kosongkan</button>
          </div>
        </div>

        <input id="pickSearch" type="text" placeholder="Cari nama / SN / IP..." class="w-full rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none mb-3">

        <div id="pickList" class="max-h-[320px] overflow-auto rounded-2xl border border-zinc-200 bg-white divide-y divide-zinc-100">
          @forelse($deviceGates as $dg)
          @php $isChecked = in_array($dg->id, old('device_ids', $selected ?? [])); @endphp
          <label data-pick-row data-name="{{ strtolower($dg->name.' '.$dg->sn.' '.$dg->ip) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-red-50/40 cursor-pointer transition {{ $isChecked ? 'bg-red-50/60' : '' }}">
            <input type="checkbox" name="device_ids[]" value="{{ $dg->id }}" {{ $isChecked ? 'checked' : '' }} class="h-4 w-4 rounded border-zinc-300 text-fq-600 focus:ring-fq-500 shrink-0" onchange="updateCount(this)">
            <span class="h-2 w-2 rounded-full shrink-0 {{ $dg->is_connected ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,.5)]' : 'bg-red-400' }}"></span>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-bold leading-tight truncate">{{ $dg->name ?: '(No Name)' }} <span class="font-mono text-[11px] font-normal text-zinc-400">· {{ $dg->sn ?: '-' }}</span></div>
              <div class="text-[11px] text-zinc-500 font-mono truncate">{{ $dg->ip ?: 'no-ip' }} · <span class="px-1.5 py-0.5 rounded text-[10px] font-bold border {{ $dg->type_badge }}">{{ $dg->type_label }}</span> · {{ $dg->stat_label }} · group {{ $dg->groupid }}</div>
            </div>
            @if($dg->groupid && $dg->groupid != ($g->id ?? 0))
            <span class="text-[10px] font-bold px-2 py-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 shrink-0">group {{ $dg->groupid }}</span>
            @endif
          </label>
          @empty
          <div class="px-4 py-10 text-center text-sm text-zinc-500">Belum ada device gate — <a href="{{ route('devicegate.create') }}" class="text-fq-600 font-bold hover:underline">tambah device</a> dulu.</div>
          @endforelse
        </div>
        @error('device_ids')<span class="text-xs text-red-600">{{ $message }}</span>@enderror
        <div class="text-[11px] text-zinc-400 mt-2">Device yang sudah punya group lain tetap bisa dipilih — akan dipindahkan ke group ini.</div>
      </div>

      <div class="flex items-center gap-2 pt-1">
        <a href="{{ route('devicegroup.index') }}" class="px-5 py-2.5 rounded-full bg-white border border-zinc-200 text-sm font-semibold hover:bg-zinc-50 transition">Batal</a>
        <button class="px-6 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold shadow hover:bg-fq-700 transition">{{ $mode==='edit' ? 'Simpan Perubahan' : 'Tambah Group' }}</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
function updateCount(el){
  if(el) el.closest('label').classList.toggle('bg-red-50/60', el.checked);
  const c = document.querySelectorAll('input[name="device_ids[]"]:checked').length;
  document.getElementById('pickCount').textContent = c;
}
function pickAll(v){
  document.querySelectorAll('input[name="device_ids[]"]').forEach(cb=>{
    cb.checked=v;
    cb.closest('label').classList.toggle('bg-red-50/60', v);
  });
  updateCount();
}
document.getElementById('pickSearch')?.addEventListener('input', function(){
  const q = this.value.toLowerCase().trim();
  document.querySelectorAll('[data-pick-row]').forEach(row=>{
    row.style.display = !q || row.dataset.name.includes(q) ? '' : 'none';
  });
});
document.addEventListener('DOMContentLoaded', updateCount);
</script>
@endpush
@endsection
