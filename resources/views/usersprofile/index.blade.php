@extends('layouts.app')
@section('title', 'Users Profile')
@section('breadcrumb','Master / Users Profile')
@section('content')
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4 mb-6">
  <div>
    <h2 class="font-serif text-[26px] sm:text-[30px] leading-none tracking-tight">Users <span class="font-space font-bold text-fq-600">Profile</span></h2>
    <p class="mt-2 text-sm text-zinc-500 max-w-[60ch] leading-relaxed">Data staff dan member gym untuk akses gateway — wajib punya departemen & branch.</p>
  </div>
  <div class="flex flex-wrap items-center gap-2">
    <button type="button" onclick="openDeviceModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full bg-zinc-900 text-white text-sm font-bold shadow hover:bg-black transition">
      <span>🖧</span> Kelola Mesin
    </button>
    <button type="button" onclick="openImportModal()" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200 text-sm font-bold shadow-sm hover:bg-indigo-100 transition">
      <span>📥</span> Import Excel
    </button>
    <a href="{{ route('usersprofile.exportExcel', request()->query()) }}" target="_blank" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm font-bold shadow-sm hover:bg-emerald-100 transition">
      <span>📊</span> Export Excel
    </a>
    <a href="{{ route('usersprofile.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-full bg-fq-600 text-white text-sm font-bold shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition">+ Tambah</a>
  </div>
</div>

@if(session('import_warnings'))
<div class="mb-4 rounded-[18px] bg-amber-50 border border-amber-200 p-4 text-xs text-amber-900">
  <div class="font-bold flex items-center gap-1.5 mb-1 text-sm text-amber-800">
    <span>⚠️</span> Beberapa Baris Dilewati saat Import:
  </div>
  <div class="space-y-0.5 leading-relaxed font-mono">
    {!! session('import_warnings') !!}
  </div>
</div>
@endif

<div class="glass rounded-[22px] p-4 mb-4">
  <form method="GET" class="flex flex-wrap gap-2">
    <input name="search" value="{{ request('search') }}" placeholder="Cari nama / kartu / identitas..." class="flex-1 min-w-[200px] rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none">
    <select name="Branchid" class="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 outline-none w-36">
      <option value="">Semua Cabang</option>
      @foreach($branches as $b)
      <option value="{{ $b->id }}" {{ request('Branchid')==$b->id ? 'selected':'' }}>{{ $b->name }}</option>
      @endforeach
    </select>
    <select name="Depid" class="rounded-full border border-zinc-200 bg-white px-4 py-2 text-sm font-semibold text-zinc-700 outline-none w-40">
      <option value="">Semua Dept</option>
      @foreach($departemens as $d)
      <option value="{{ $d->id }}" {{ request('Depid')==$d->id ? 'selected':'' }}>{{ $d->name }}</option>
      @endforeach
    </select>
    <button class="px-5 py-2 rounded-full bg-zinc-900 text-white text-sm font-bold hover:bg-black">Cari</button>
    @if(request()->anyFilled(['search','Branchid','Depid']))<a href="{{ route('usersprofile.index') }}" class="px-3 py-2 text-sm font-semibold text-zinc-500 hover:text-fq-600">Reset</a>@endif
  </form>
</div>

{{-- Bulk action bar --}}
<div id="bulkBar" class="hidden glass rounded-[16px] px-4 py-3 mb-3 flex flex-wrap items-center gap-3 border-fq-200 bg-red-50/60">
  <span class="text-sm font-bold text-zinc-800"><span id="bulkCount">0</span> user dicentang</span>
  <span class="text-zinc-300">·</span>
  <button type="button" onclick="clearSelection()" class="text-xs font-semibold text-zinc-500 hover:text-zinc-700">Batal pilih</button>
  <div class="ml-auto flex items-center gap-2">
    <button type="button" onclick="openDeviceModal()" class="px-4 py-1.5 rounded-full bg-zinc-900 text-white text-xs font-bold hover:bg-black transition shadow">🖧 Kirim / Hapus ke Mesin</button>
    <!-- <button type="button" onclick="bulkDelete()" class="px-4 py-1.5 rounded-full bg-red-600 text-white text-xs font-bold hover:bg-red-700 transition shadow">🗑️ Hapus Database</button> -->
  </div>
</div>

<div class="glass rounded-[24px] overflow-hidden">
  <div class="overflow-auto">
    <table class="w-full text-sm">
      <thead class="bg-red-50/50 text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">
        <tr>
          <th class="px-3 py-3 text-center w-10">
            <input type="checkbox" id="checkAll" class="h-4 w-4 rounded border-zinc-300 text-fq-600 focus:ring-fq-500">
          </th>
          <th class="px-3 py-3 text-left w-14">Foto</th>
          <th class="px-4 py-3 text-left">Nama / Identitas</th>
          <th class="px-4 py-3 text-left">Kartu / ID Personel</th>
          <th class="px-4 py-3 text-left">Branch / Dept</th>
          <th class="px-4 py-3 text-left">Tipe</th>
          <th class="px-4 py-3 text-left">Berlaku</th>
          <th class="px-4 py-3 text-right">Aksi</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-zinc-100">
        @forelse($users as $u)
        <tr class="hover:bg-red-50/30 transition group" data-row="{{ $u->ID }}">
          <td class="px-3 py-3 text-center">
            <input type="checkbox" value="{{ $u->ID }}" data-name="{{ $u->NAME }}" class="row-check h-4 w-4 rounded border-zinc-300 text-fq-600 focus:ring-fq-500">
          </td>
          <td class="px-3 py-3">
            <div class="h-9 w-9 rounded-xl bg-zinc-100 border border-zinc-200 overflow-hidden shrink-0">
              @if($u->has_photo)
              <img src="{{ route('usersprofile.photo', $u) }}" class="h-full w-full object-cover">
              @else
              <div class="h-full w-full flex items-center justify-center text-zinc-400 text-sm font-bold bg-zinc-50">{{ strtoupper(substr($u->NAME,0,1)) }}</div>
              @endif
            </div>
          </td>
          <td class="px-4 py-3">
            <div class="font-bold text-zinc-800 leading-tight">{{ $u->NAME ?: '(No Name)' }}</div>
            <div class="text-[11px] font-mono text-zinc-500 mt-0.5">NIK: {{ $u->NoIdentitas ?: '—' }} · ID: #{{ $u->ID }}</div>
          </td>
          <td class="px-4 py-3 font-mono text-[11px] text-zinc-600">
            <div>RFID: <span class="font-bold text-zinc-800">{{ $u->Card ?: '—' }}</span></div>
            <div class="opacity-70 mt-0.5">NIK: {{ $u->EMPLOYEE_ID ?: '—' }}</div>
          </td>
          <td class="px-4 py-3 text-[11px]">
            <div class="font-semibold text-zinc-700 truncate max-w-[140px]">🏢 {{ $u->branch->name ?? '—' }}</div>
            <div class="text-zinc-500 truncate max-w-[140px]">🗂️ {{ $u->departemen->name ?? '—' }}</div>
          </td>
          <td class="px-4 py-3">
            @if((int)$u->user_type===1)
              <span class="px-2.5 py-0.5 rounded-full border border-emerald-200 bg-emerald-50 text-[10px] font-bold text-emerald-700">MEMBER</span>
            @elseif((int)$u->user_type===2)
              <span class="px-2.5 py-0.5 rounded-full border border-blue-200 bg-blue-50 text-[10px] font-bold text-blue-700">KARYAWAN</span>
            @else
              <span class="px-2 py-0.5 rounded-full border border-zinc-200 bg-zinc-50 text-[10px] font-bold text-zinc-500">Tipe {{ $u->user_type }}</span>
            @endif
          </td>
          <td class="px-4 py-3 text-[11px] text-zinc-500 leading-tight">
            <div>{{ $u->BEGIN_DATE ? $u->BEGIN_DATE->format('d/m/Y') : '—' }}</div>
            <div class="opacity-60">s/d {{ $u->END_DATE ? $u->END_DATE->format('d/m/Y') : '—' }}</div>
          </td>
          <td class="px-4 py-3 text-right whitespace-nowrap">
            <div class="flex items-center justify-end gap-1">
              <a href="{{ route('usersprofile.show',$u) }}" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-zinc-600 hover:bg-zinc-50 flex items-center justify-center transition" title="Detail">👁️</a>
              @if($u->Card)
              <!-- <button type="button" onclick="downloadQrRow('{{ $u->Card }}','{{ addslashes($u->NAME) }}')" class="h-8 w-8 rounded-xl bg-zinc-900 text-white hover:bg-black flex items-center justify-center transition" title="Download QR">⬇</button> -->
              @else
              <span class="h-8 w-8 rounded-xl bg-zinc-100 border border-zinc-200 text-zinc-300 flex items-center justify-center cursor-not-allowed" title="Belum ada kartu">⬇</span>
              @endif
              <a href="{{ route('usersprofile.edit',$u) }}" class="h-8 w-8 rounded-xl bg-white border border-zinc-200 text-zinc-700 hover:bg-zinc-50 flex items-center justify-center transition" title="Edit">✏️</a>
              <form method="POST" action="{{ route('usersprofile.destroy',$u) }}" onsubmit="return confirm('Hapus {{ $u->NAME }}?')" class="inline">
                @csrf @method('DELETE')
                <button class="h-8 w-8 rounded-xl bg-red-50 border border-red-200 text-red-600 hover:bg-red-100 flex items-center justify-center transition" title="Hapus">🗑️</button>
              </form>
            </div>
          </td>
        </tr>
        @empty
        <tr><td colspan="8" class="px-4 py-12 text-center"><div class="font-bold">Belum ada user</div><div class="text-xs text-zinc-500 mt-1">Tambahkan user pertama.</div></td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  <div class="px-5 py-3 border-t border-zinc-100 bg-white/60 flex flex-wrap items-center gap-3">
    <div class="text-xs text-zinc-500">Total <span class="font-bold text-zinc-800">{{ $stats['total'] }}</span> user</div>
    <div class="ml-auto">{{ $users->links() }}</div>
  </div>
</div>

{{-- Hidden bulk form --}}
<form id="bulkForm" method="POST" action="{{ route('usersprofile.bulkDestroy') }}" class="hidden">
  @csrf
  <div id="bulkIds"></div>
</form>

{{-- ================= MODAL DEVICE GATE & DEVICE GROUP (SYNC & DELETE MESIN) ================= --}}
<div id="deviceModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4">
  <div class="absolute inset-0 bg-zinc-900/40 backdrop-blur-sm" onclick="closeDeviceModal()"></div>
  <div class="relative glass rounded-[28px] p-6 sm:p-7 w-full max-w-2xl shadow-2xl scale-95 opacity-0 transition-all duration-200 max-h-[90vh] flex flex-col" id="deviceBox">
    
    {{-- Modal Header --}}
    <div class="flex items-start justify-between pb-4 border-b border-zinc-100">
      <div>
        <div class="font-space font-bold text-lg sm:text-xl text-zinc-900">Kirim / Hapus Data ke Mesin Device</div>
        <p class="text-xs text-zinc-500 mt-0.5">Pilih target <span class="font-semibold text-zinc-700">Device Gate</span> atau <span class="font-semibold text-zinc-700">Device Group</span> untuk user yang dicentang.</p>
      </div>
      <button onclick="closeDeviceModal()" class="h-8 w-8 rounded-full bg-zinc-100 hover:bg-zinc-200 flex items-center justify-center text-zinc-500 transition shrink-0">✕</button>
    </div>

    {{-- Modal Body (Scrollable) --}}
    <div class="overflow-y-auto py-4 space-y-5 flex-1 pr-1">
      
      {{-- User terpilih banner --}}
      <div class="p-3 rounded-2xl bg-zinc-50 border border-zinc-200 flex items-center justify-between gap-3">
        <div class="text-xs text-zinc-600">
          <span class="font-bold text-fq-600 text-sm" id="modalUserCount">0</span> user dicentang di tabel
        </div>
        <span id="modalUserNames" class="text-[11px] text-zinc-400 font-mono truncate max-w-[340px]"></span>
      </div>

      {{-- Tabs: Device Gate vs Device Group --}}
      <div>
        <div class="flex items-center justify-between mb-2">
          <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">1. Pilih Target Mesin</span>
          <div class="flex items-center gap-1">
            <button type="button" onclick="checkAllGates(true)" class="text-[11px] font-bold text-fq-600 hover:underline">Pilih semua gate</button>
            <span class="text-zinc-300">·</span>
            <button type="button" onclick="checkAllGates(false)" class="text-[11px] text-zinc-400 hover:underline">Kosongkan</button>
          </div>
        </div>

        {{-- Section Device Group --}}
        @if($deviceGroups->count())
        <div class="mb-3">
          <div class="text-[11px] font-bold text-zinc-500 mb-1.5 uppercase tracking-wider">📑 Device Group (Otomatis memilih semua gate di group)</div>
          <div class="grid sm:grid-cols-2 gap-2">
            @foreach($deviceGroups as $g)
            <label class="flex items-center gap-2.5 p-2.5 rounded-2xl border border-zinc-200 bg-white hover:bg-red-50/40 cursor-pointer transition">
              <input type="checkbox" name="modal_group_ids[]" value="{{ $g->id }}" class="h-4 w-4 rounded border-zinc-300 text-fq-600 focus:ring-fq-500 group-cb" onchange="onGroupToggle(this)">
              <div class="min-w-0 flex-1">
                <div class="text-xs font-bold text-zinc-800 truncate">{{ $g->name }} <span class="font-mono text-[10px] text-zinc-400">#{{ $g->number }}</span></div>
                <div class="text-[10px] text-zinc-500">{{ $g->device_gates_count }} device terhubung</div>
              </div>
            </label>
            @endforeach
          </div>
        </div>
        @endif

        {{-- Section Individual Device Gate --}}
        <div>
          <div class="text-[11px] font-bold text-zinc-500 mb-1.5 uppercase tracking-wider">🖧 Device Gate (Pilih per pintu)</div>
          <div class="max-h-[220px] overflow-y-auto rounded-2xl border border-zinc-200 bg-white divide-y divide-zinc-100">
            @forelse($deviceGates as $dg)
            <label class="flex items-center gap-3 px-3.5 py-2.5 hover:bg-red-50/40 cursor-pointer transition gate-row" data-groupid="{{ $dg->groupid }}">
              <input type="checkbox" name="modal_gate_ids[]" value="{{ $dg->id }}" class="h-4 w-4 rounded border-zinc-300 text-fq-600 focus:ring-fq-500 gate-cb shrink-0" onchange="updateSelectedCount()">
              <span class="h-2 w-2 rounded-full shrink-0 {{ $dg->is_connected ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,.5)]' : 'bg-red-400' }}"></span>
              <div class="min-w-0 flex-1">
                <div class="text-xs font-bold text-zinc-800 truncate">{{ $dg->name ?: '(No Name)' }} <span class="font-mono text-[11px] font-normal text-zinc-400">· SN: {{ $dg->sn ?: '—' }}</span></div>
                <div class="text-[10px] text-zinc-500 font-mono">{{ $dg->ip ?: 'no-ip' }} · <span class="font-semibold">{{ $dg->type_label }}</span> · {{ $dg->stat_label }} @if($dg->groupid)· group #{{ $dg->groupid }}@endif</div>
              </div>
            </label>
            @empty
            <div class="p-4 text-center text-xs text-zinc-400">Belum ada data device gate.</div>
            @endforelse
          </div>
        </div>
      </div>

      {{-- Deskripsi API payload info --}}
      <div class="p-3.5 rounded-2xl bg-zinc-900 text-zinc-100 text-[11px] space-y-1">
        <div class="font-bold text-white flex items-center gap-1.5">
          <span>⚡</span> Alur Eksekusi API Mesin Tasoft:
        </div>
        <div class="text-zinc-300">
          • <span class="text-emerald-400 font-semibold">Kirim ke Mesin</span>: Panggil <code class="text-amber-300 bg-white/10 px-1 rounded">#2 /registerface</code> (SN, UserID, Name, Card, Photo Base64) ➜ Panggil <code class="text-amber-300 bg-white/10 px-1 rounded">#3 /useraccess</code> (SN, UserID, Begin/End Date) ➜ Simpan ke <code class="text-sky-300">UsersXDevice</code>.
        </div>
        <div class="text-zinc-300">
          • <span class="text-red-400 font-semibold">Hapus dari Mesin</span>: Panggil <code class="text-amber-300 bg-white/10 px-1 rounded">#6 /deleteface</code> (SN, UserID) ➜ Hapus relasi <code class="text-sky-300">UsersXDevice</code>.
        </div>
      </div>

      {{-- Live Progress Bar Eksekusi Real-Time --}}
      <div id="syncProgressContainer" class="hidden p-4 rounded-2xl bg-zinc-900 border border-zinc-800 text-white space-y-2.5">
        <div class="flex items-center justify-between text-xs">
          <div class="flex items-center gap-2">
            <span id="syncProgressSpinner" class="animate-spin text-sm">⏳</span>
            <span id="syncProgressTitle" class="font-bold text-zinc-100">Sedang memproses ke mesin...</span>
          </div>
          <span id="syncProgressRatio" class="font-mono font-bold text-fq-400">0 / 0</span>
        </div>

        <div class="w-full bg-zinc-800 h-2.5 rounded-full overflow-hidden p-0.5 border border-zinc-700">
          <div id="syncProgressBar" class="bg-gradient-to-r from-red-600 via-fq-500 to-emerald-400 h-full rounded-full transition-all duration-300" style="width: 0%"></div>
        </div>

        <div class="flex items-center justify-between text-[11px] text-zinc-400">
          <span id="syncProgressCurrent">Menyiapkan data...</span>
          <div class="flex items-center gap-3 font-mono">
            <span class="text-emerald-400">✓ <span id="syncStatSuccess">0</span></span>
            <span class="text-red-400">✗ <span id="syncStatFail">0</span></span>
          </div>
        </div>
      </div>

      {{-- Result Log Box (Live response) --}}
      <div id="modalResultBox" class="hidden space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold uppercase tracking-wider text-zinc-600">Hasil Respon Mesin:</span>
          <span id="modalResultBadge" class="text-xs font-bold px-2 py-0.5 rounded-full"></span>
        </div>
        <pre id="modalResultText" class="p-3 rounded-2xl bg-zinc-900 text-zinc-200 font-mono text-[10.5px] max-h-[160px] overflow-auto whitespace-pre-wrap break-words"></pre>
      </div>

    </div>

    {{-- Modal Footer Buttons --}}
    <div class="pt-4 border-t border-zinc-100 flex flex-wrap items-center justify-between gap-2">
      <button type="button" onclick="closeDeviceModal()" class="px-5 py-2.5 rounded-full bg-white border border-zinc-200 text-zinc-700 text-xs font-bold hover:bg-zinc-50 transition">Tutup</button>
      
      <div class="flex items-center gap-2">
        <button id="btnRemoveDevice" type="button" onclick="submitDeviceAction('remove')" class="px-5 py-2.5 rounded-full bg-red-50 border border-red-200 text-red-700 text-xs font-bold hover:bg-red-100 transition flex items-center gap-1.5 shadow-sm">
          <span>🗑️</span> Hapus dari Mesin (#6)
        </button>
        <button id="btnSyncDevice" type="button" onclick="submitDeviceAction('sync')" class="px-6 py-2.5 rounded-full bg-fq-600 text-white text-xs font-bold hover:bg-fq-700 transition flex items-center gap-1.5 shadow-[0_8px_20px_rgba(220,38,38,.28)]">
          <span>🚀</span> Kirim ke Mesin (#2 & #3)
        </button>
      </div>
    </div>

  </div>
</div>

{{-- Modal Import Excel & Foto ZIP --}}
<div id="importModal" class="fixed inset-0 z-50 hidden bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
  <div class="bg-white rounded-[28px] max-w-lg w-full shadow-2xl border border-zinc-100 overflow-hidden transform transition-all">
    <div class="px-6 py-5 bg-gradient-to-r from-indigo-900 to-indigo-800 text-white flex items-center justify-between">
      <div class="flex items-center gap-3">
        <span class="text-2xl">📥</span>
        <div>
          <h3 class="font-bold text-base tracking-tight">Import Data Users Profile</h3>
          <p class="text-xs text-indigo-200">Import file Excel (.xlsx / .csv) & arsip foto ZIP</p>
        </div>
      </div>
      <button type="button" onclick="closeImportModal()" class="text-indigo-200 hover:text-white text-xl font-bold w-8 h-8 rounded-full flex items-center justify-center hover:bg-white/10 transition">&times;</button>
    </div>

    <form action="{{ route('usersprofile.importExcel') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5" onsubmit="handleImportSubmit(event)">
      @csrf

      <div class="bg-indigo-50/70 border border-indigo-100 rounded-[18px] p-4 flex items-center justify-between">
        <div>
          <div class="text-xs font-bold text-indigo-950">Belum punya template Excel?</div>
          <div class="text-[11px] text-indigo-700 mt-0.5">Download template resmi yang sudah terformat rapi.</div>
        </div>
        <a href="{{ route('usersprofile.downloadTemplate') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-full bg-indigo-600 text-white text-xs font-bold shadow hover:bg-indigo-700 transition shrink-0">
          <span>⬇️</span> Download Template
        </a>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase tracking-widest text-zinc-600 mb-1.5">
          1. File Excel (.xlsx / .csv) <span class="text-red-500">*</span>
        </label>
        <div class="border-2 border-dashed border-zinc-300 hover:border-indigo-400 rounded-2xl p-4 text-center cursor-pointer bg-zinc-50/50 hover:bg-indigo-50/20 transition relative">
          <input type="file" name="excel_file" id="excelFile" accept=".xlsx,.csv,.txt" required class="absolute inset-0 opacity-0 cursor-pointer" onchange="updateFileLabel('excelFile', 'excelFileLabel')">
          <div id="excelFileLabel" class="text-xs text-zinc-600">
            <span class="font-bold text-indigo-600">Pilih file Excel</span> atau seret ke sini
            <p class="text-[10px] text-zinc-400 mt-1">Format: .xlsx atau .csv (Maksimal 20 MB)</p>
          </div>
        </div>
      </div>

      <div>
        <div class="flex items-center justify-between mb-1.5">
          <label class="block text-xs font-bold uppercase tracking-widest text-zinc-600">
            2. File ZIP Foto Wajah <span class="text-zinc-400 font-normal lowercase">(opsional)</span>
          </label>
          <span class="text-[10px] bg-zinc-100 text-zinc-600 px-2 py-0.5 rounded-full font-semibold">Nama file = EMPLOYEE_ID</span>
        </div>
        <div class="border-2 border-dashed border-zinc-300 hover:border-indigo-400 rounded-2xl p-4 text-center cursor-pointer bg-zinc-50/50 hover:bg-indigo-50/20 transition relative">
          <input type="file" name="zip_file" id="zipFile" accept=".zip" class="absolute inset-0 opacity-0 cursor-pointer" onchange="updateFileLabel('zipFile', 'zipFileLabel')">
          <div id="zipFileLabel" class="text-xs text-zinc-600">
            <span class="font-bold text-indigo-600">Pilih file arsip ZIP</span> foto wajah
            <p class="text-[10px] text-zinc-400 mt-1">Contoh: EMP001.jpg, EMP002.png (Maksimal 100 MB)</p>
          </div>
        </div>
      </div>

      <div class="rounded-xl bg-zinc-50 border border-zinc-200/80 p-3 text-[11px] text-zinc-500 space-y-1">
        <div class="font-bold text-zinc-700">Petunjuk Import:</div>
        <div>• Jika <span class="font-mono text-zinc-800">EMPLOYEE_ID</span> sudah ada di database, data akan <b>di-update</b>. Jika belum, akan <b>dibuat baru</b>.</div>
        <div>• Sistem otomatis mengompresi foto dari ZIP menjadi format JPEG 20–100KB standar mesin turnstile.</div>
        <div>• Nomor Kartu (Card) dan No. Identitas tidak boleh bernilai sama.</div>
      </div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-100">
        <button type="button" onclick="closeImportModal()" class="px-4 py-2 rounded-full border border-zinc-300 text-zinc-600 text-xs font-bold hover:bg-zinc-100 transition">Batal</button>
        <button type="submit" id="btnSubmitImport" class="px-5 py-2 rounded-full bg-indigo-600 text-white text-xs font-bold hover:bg-indigo-700 shadow-md transition flex items-center gap-1.5">
          <span>🚀</span> Mulai Import Data
        </button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function openImportModal(){
  document.getElementById('importModal').classList.remove('hidden');
}
function closeImportModal(){
  document.getElementById('importModal').classList.add('hidden');
}
function updateFileLabel(inputId, labelId){
  const input = document.getElementById(inputId);
  const label = document.getElementById(labelId);
  if(input && input.files && input.files[0]){
    const file = input.files[0];
    const sizeMb = (file.size / (1024*1024)).toFixed(2);
    label.innerHTML = `<span class="font-bold text-indigo-700">✓ ${file.name}</span> <span class="text-zinc-400">(${sizeMb} MB)</span>`;
  }
}
function handleImportSubmit(e){
  const btn = document.getElementById('btnSubmitImport');
  if(btn){
    btn.disabled = true;
    btn.innerHTML = '⏳ Mengunggah & Memproses...';
  }
}
function downloadQrRow(card, name){
  if(!card) return;
  const tempDiv=document.createElement('div');
  tempDiv.style.position='fixed'; tempDiv.style.left='-9999px'; tempDiv.style.top='-9999px';
  document.body.appendChild(tempDiv);
  try{
    new QRCode(tempDiv,{text: card.toString(), width:400, height:400, colorDark:'#000000', colorLight:'#ffffff', correctLevel: QRCode.CorrectLevel.H});
    setTimeout(()=>{
      const qrCanvas=tempDiv.querySelector('canvas');
      if(qrCanvas){
        const padding=24, textH=36;
        const c=document.createElement('canvas'), ctx=c.getContext('2d');
        const w=qrCanvas.width+padding*2, h=qrCanvas.height+padding*2+textH;
        c.width=w; c.height=h;
        ctx.fillStyle='#FFFFFF'; ctx.fillRect(0,0,w,h);
        ctx.drawImage(qrCanvas, padding, padding);
        ctx.font='bold 16px Plus Jakarta Sans, Arial'; ctx.fillStyle='#111827'; ctx.textAlign='center';
        ctx.fillText(name||'User', w/2, h-14);
        ctx.font='11px Plus Jakarta Sans, Arial'; ctx.fillStyle='#6b7280';
        ctx.fillText(card, w/2, h-6);
        const url=c.toDataURL('image/jpeg',1.0);
        const a=document.createElement('a'); a.href=url; a.download=((name||'User').replace(/\s+/g,'_')+'_QRCODE.jpg'); document.body.appendChild(a); a.click(); a.remove();
      }
      tempDiv.remove();
    },600);
  }catch(e){ console.error(e); tempDiv.remove(); }
}

const checkAll = document.getElementById('checkAll');
const bulkBar = document.getElementById('bulkBar');
const bulkCount = document.getElementById('bulkCount');

function updateBulk(){
  const checked = document.querySelectorAll('.row-check:checked');
  const n = checked.length;
  bulkCount.textContent = n;
  bulkBar.classList.toggle('hidden', n===0);
  bulkBar.classList.toggle('flex', n>0);
  document.querySelectorAll('tbody tr[data-row]').forEach(tr=>{
    const cb = tr.querySelector('.row-check');
    tr.classList.toggle('bg-red-50/50', cb && cb.checked);
  });
  const all = document.querySelectorAll('.row-check');
  if(all.length) checkAll.checked = n===all.length && n>0;
  checkAll.indeterminate = n>0 && n<all.length;
}

checkAll?.addEventListener('change', function(){
  document.querySelectorAll('.row-check').forEach(cb=> cb.checked = this.checked);
  updateBulk();
});
document.querySelectorAll('.row-check').forEach(cb=> cb.addEventListener('change', updateBulk));

function clearSelection(){
  document.querySelectorAll('.row-check').forEach(cb=> cb.checked=false);
  checkAll.checked=false; checkAll.indeterminate=false;
  updateBulk();
}
function bulkDelete(){
  const ids = [...document.querySelectorAll('.row-check:checked')].map(cb=>cb.value);
  if(!ids.length) return;
  if(!confirm('Hapus '+ids.length+' user terpilih dari database?')) return;
  const container = document.getElementById('bulkIds');
  container.innerHTML = '';
  ids.forEach(id=>{
    const inp = document.createElement('input');
    inp.type='hidden'; inp.name='ids[]'; inp.value=id;
    container.appendChild(inp);
  });
  document.getElementById('bulkForm').submit();
}

/* ================= MODAL DEVICE SYNC LOGIC ================= */
function openDeviceModal(){
  const checkedUsers = document.querySelectorAll('.row-check:checked');
  if(checkedUsers.length === 0){
    alert('⚠️ Harap checklist minimal 1 user di tabel terlebih dahulu!');
    return;
  }
  document.getElementById('modalUserCount').textContent = checkedUsers.length;
  const names = [...checkedUsers].map(cb => cb.dataset.name || cb.value).slice(0, 5).join(', ');
  document.getElementById('modalUserNames').textContent = names + (checkedUsers.length > 5 ? ' + ' + (checkedUsers.length - 5) + ' lainnya' : '');
  
  // reset result
  document.getElementById('modalResultBox').classList.add('hidden');
  
  document.getElementById('deviceModal').classList.remove('hidden');
  setTimeout(()=> document.getElementById('deviceBox').classList.remove('scale-95','opacity-0'), 10);
}

function closeDeviceModal(){
  document.getElementById('deviceBox').classList.add('scale-95','opacity-0');
  setTimeout(()=> document.getElementById('deviceModal').classList.add('hidden'), 200);
}

function checkAllGates(val){
  document.querySelectorAll('.gate-cb').forEach(cb => cb.checked = val);
}

function onGroupToggle(groupCb){
  const gid = groupCb.value;
  const isChecked = groupCb.checked;
  // check all gates that have this groupid
  document.querySelectorAll(`.gate-row[data-groupid="${gid}"] .gate-cb`).forEach(cb => {
    cb.checked = isChecked;
  });
}

function updateSelectedCount(){
  // optional
}

async function submitDeviceAction(actionType){
  const userCheckboxes = [...document.querySelectorAll('.row-check:checked')];
  const userIds = userCheckboxes.map(cb => parseInt(cb.value));

  // Ambil daftar gate id yang dicentang secara langsung maupun via group
  let gateIds = [...document.querySelectorAll('.gate-cb:checked')].map(cb => parseInt(cb.value));
  const groupCheckboxes = [...document.querySelectorAll('.group-cb:checked')];
  if(groupCheckboxes.length > 0){
    const groupIds = groupCheckboxes.map(cb => parseInt(cb.value));
    document.querySelectorAll('.gate-row').forEach(row => {
      const gId = parseInt(row.getAttribute('data-groupid'));
      if(groupIds.includes(gId)){
        const gCb = row.querySelector('.gate-cb');
        if(gCb) gateIds.push(parseInt(gCb.value));
      }
    });
  }
  gateIds = [...new Set(gateIds)].filter(id => !isNaN(id) && id > 0);

  if(userIds.length === 0){
    alert('⚠️ Checklist minimal 1 user terlebih dahulu!');
    return;
  }
  if(gateIds.length === 0){
    alert('⚠️ Pilih minimal 1 Device Gate atau Device Group!');
    return;
  }

  const isSync = actionType === 'sync';
  const total = userIds.length;
  const actionLabel = isSync ? 'Mengirim' : 'Menghapus';
  const confirmMsg = `${actionLabel} data ${total} user terpilih ke ${gateIds.length} device gate secara bertahap?`;

  if(!confirm(confirmMsg)) return;

  const btnSync = document.getElementById('btnSyncDevice');
  const btnRemove = document.getElementById('btnRemoveDevice');
  const activeBtn = isSync ? btnSync : btnRemove;
  const oriText = activeBtn.innerHTML;

  btnSync.disabled = true;
  btnRemove.disabled = true;

  // Inisialisasi UI Progress Bar
  const progContainer = document.getElementById('syncProgressContainer');
  const progTitle     = document.getElementById('syncProgressTitle');
  const progRatio     = document.getElementById('syncProgressRatio');
  const progBar       = document.getElementById('syncProgressBar');
  const progCurrent   = document.getElementById('syncProgressCurrent');
  const statSuccess   = document.getElementById('syncStatSuccess');
  const statFail      = document.getElementById('syncStatFail');
  const progSpinner   = document.getElementById('syncProgressSpinner');

  const resultBox     = document.getElementById('modalResultBox');
  const resultBadge   = document.getElementById('modalResultBadge');
  const resultText    = document.getElementById('modalResultText');

  progContainer.classList.remove('hidden');
  progSpinner.classList.add('animate-spin');
  progTitle.textContent = `${actionLabel} ke mesin turnstile...`;
  progRatio.textContent = `0 / ${total}`;
  progBar.style.width = '0%';
  progCurrent.textContent = 'Memulai proses...';
  statSuccess.textContent = '0';
  statFail.textContent = '0';

  resultBox.classList.remove('hidden');
  resultBadge.textContent = '⏳ SEDANG BERJALAN';
  resultBadge.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700';
  resultText.textContent = `[${new Date().toLocaleTimeString()}] Memulai ${actionLabel.toLowerCase()} ${total} user ke ${gateIds.length} gate...\n`;

  const stepEndpoint = isSync ? '{{ route("usersprofile.syncStep") }}' : '{{ route("usersprofile.removeStep") }}';
  let successCount = 0;
  let failCount = 0;
  const detailedLogs = [];

  for(let i = 0; i < total; i++){
    const uid = userIds[i];
    const currentNum = i + 1;
    const pct = Math.round((currentNum / total) * 100);

    // Ambil nama user dari baris tabel jika ada
    const userRow = userCheckboxes[i]?.closest('tr');
    const userNameHint = userRow ? (userRow.querySelector('.font-bold')?.textContent?.trim() || `User #${uid}`) : `User #${uid}`;
    progCurrent.textContent = `[${currentNum}/${total}] Memproses ${userNameHint}...`;

    try {
      const res = await fetch(stepEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json'
        },
        body: JSON.stringify({
          user_id: uid,
          gate_ids: gateIds
        })
      });

      const resData = await res.json();
      if(resData.ok){
        successCount++;
        statSuccess.textContent = successCount;
        resultText.textContent += `✓ [${currentNum}/${total}] Sukses: ${resData.user_name} (${resData.emp_id})\n`;
      } else {
        failCount++;
        statFail.textContent = failCount;
        resultText.textContent += `✗ [${currentNum}/${total}] Gagal: ${resData.user_name || userNameHint} — ${resData.message || 'Error API'}\n`;
      }
      detailedLogs.push(resData);
    } catch(err) {
      failCount++;
      statFail.textContent = failCount;
      resultText.textContent += `✗ [${currentNum}/${total}] Error Network: ${userNameHint} (${err.message})\n`;
    }

    // Update Progress Bar
    progBar.style.width = `${pct}%`;
    progRatio.textContent = `${currentNum} / ${total} (${pct}%)`;
    resultText.scrollTop = resultText.scrollHeight;
  }

  progSpinner.classList.remove('animate-spin');
  progTitle.textContent = failCount === 0 ? '✓ Selesai Semua!' : '⚠️ Selesai dengan catatan';
  progCurrent.textContent = `Proses tuntas: ${successCount} berhasil, ${failCount} gagal.`;

  resultBadge.textContent = failCount === 0 ? '✓ SUKSES 100%' : '⚠️ ADA GAGAL';
  resultBadge.className = 'text-xs font-bold px-2 py-0.5 rounded-full ' + (failCount === 0 ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700');

  btnSync.disabled = false;
  btnRemove.disabled = false;
  activeBtn.innerHTML = oriText;
}
</script>
@endpush
@endsection
