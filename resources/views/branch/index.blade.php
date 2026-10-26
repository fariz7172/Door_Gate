@extends('layouts.app')
@section('title','Branch')
@section('breadcrumb','Master / Branch')
@section('content')
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
  <div>
    <h2 class="font-serif text-[26px] sm:text-[30px] leading-none tracking-tight">Branch <span class="font-space font-bold text-fq-600">Cabang</span></h2>
    <p class="mt-2 text-sm text-zinc-500 max-w-[60ch] leading-relaxed">Daftar cabang gym — kode unik <span class="font-mono text-xs bg-zinc-100 px-1.5 py-0.5 rounded border border-zinc-200">number</span> dipakai sebagai branchCode.</p>
  </div>
  <a href="{{ route('branch.create') }}" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">+ Tambah Branch</a>
</div>

<div class="glass rounded-[22px] p-4 mb-4">
  <form method="GET" class="flex gap-2">
    <input name="search" value="{{ request('search') }}" placeholder="Cari number / nama / deskripsi..." class="flex-1 rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
    <button class="px-5 py-2 rounded-full bg-zinc-900 text-white text-sm font-bold hover:bg-black">Cari</button>
    @if(request('search'))<a href="{{ route('branch.index') }}" class="px-3 py-2 text-sm font-semibold text-zinc-500 hover:text-fq-600">Reset</a>@endif
  </form>
</div>

<div class="glass rounded-[24px] overflow-hidden">
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-red-50/50 text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">
        <tr>
          <th class="px-4 py-3 text-left">Number</th>
          <th class="px-4 py-3 text-left">Nama Cabang</th>
          <th class="px-4 py-3 text-left">Deskripsi</th>
          <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-100">
        @forelse($branches as $b)
        <tr class="hover:bg-red-50/30 transition">
          <td class="px-4 py-3"><span class="inline-flex items-center justify-center h-7 min-w-[48px] rounded-full bg-zinc-900 text-white font-mono text-xs font-bold px-2">{{ $b->number ?? '-' }}</span></td>
          <td class="px-4 py-3 font-bold text-zinc-800">{{ $b->name ?: '-' }}</td>
          <td class="px-4 py-3 text-zinc-500 max-w-[360px] truncate" title="{{ $b->description }}">{{ $b->description ?: '-' }}</td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1.5">
              <a href="{{ route('branch.edit',$b) }}" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 flex items-center justify-center transition" title="Edit">✏️</a>
              <form method="POST" action="{{ route('branch.destroy',$b) }}" onsubmit="return confirm('Hapus branch {{ $b->name }}?')" class="inline">
                @csrf @method('DELETE')
                <button class="h-8 w-8 rounded-xl bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 flex items-center justify-center transition" title="Hapus">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="4" class="px-4 py-12 text-center"><div class="font-bold">Belum ada branch</div><div class="text-xs text-zinc-500 mt-1">Tambahkan cabang pertama.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="px-5 py-3 border-t border-zinc-100 bg-white/60">{{ $branches->links() }}</div>
</div>
@endsection
