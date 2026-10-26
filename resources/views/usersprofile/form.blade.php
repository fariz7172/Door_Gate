@extends('layouts.app')
@section('title', ($mode==='create'?'Tambah':'Edit').' Personel')
@section('breadcrumb','Master / Users Profile / '.($mode==='create'?'Tambah':'Edit'))
@section('content')
<div class="w-full space-y-6">
  {{-- TOP BAR --}}
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
    <div>
      <a href="{{ route('usersprofile.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400 hover:text-fq-600 transition">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
        Kembali ke Daftar Personel
      </a>
      <h2 class="mt-2 font-serif text-[28px] sm:text-[32px] leading-tight tracking-tight text-zinc-900">
        {{ $mode==='create'?'Tambah':'Edit Data' }} <span class="font-space font-bold text-fq-600">Personel</span>
      </h2>
      <p class="text-sm text-zinc-500">Kelola identitas, biometrik wajah, nomor kartu RFID, dan hak akses turnstile gateway.</p>
    </div>
  </div>

  {{-- FORM UTAMA --}}
  <form method="POST" action="{{ $mode==='create'?route('usersprofile.store'):route('usersprofile.update',$u) }}" enctype="multipart/form-data" class="w-full">
    @csrf @if($mode==='edit') @method('PUT') @endif

    @if(isset($errors) && $errors->any())
    <div class="mb-6 rounded-2xl bg-red-50 border border-red-200 text-red-800 px-5 py-3.5 text-sm font-medium flex items-center gap-3">
      <span class="text-lg">⚠️</span>
      <div>{{ $errors->first() }}</div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      
      {{-- KOLOM KIRI (4 KOLOM): FOTO & BIOMETRIK --}}
      <div class="lg:col-span-4 space-y-6">
        <div class="glass rounded-[28px] p-6 text-center space-y-4">
          <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
            <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Biometrik Wajah</span>
            <span id="photoSizeBadge" class="text-[10px] text-zinc-400 font-mono">Standar Gate: 20-100KB</span>
          </div>

          {{-- PHOTO PREVIEW BOX --}}
          <div class="relative group mx-auto w-48 h-48 sm:w-56 sm:h-56">
            <div id="photoContainer" class="w-full h-full rounded-[24px] bg-zinc-100 border-2 border-dashed border-zinc-300 overflow-hidden flex flex-col items-center justify-center relative shadow-inner transition group-hover:border-fq-400">
              @if($u->photo)
              <img id="photoImg" src="{{ route('usersprofile.photo', $u) }}" class="h-full w-full object-cover">
              @else
              <img id="photoImg" class="h-full w-full object-cover hidden">
              <div id="photoPlaceholder" class="text-center p-4">
                <span class="text-4xl block mb-2">📸</span>
                <div class="text-xs font-bold text-zinc-700">Pilih Foto Personel</div>
                <div class="text-[10px] text-zinc-400 mt-1">JPEG, PNG, atau WebP</div>
                <div class="text-[10px] text-fq-600 font-semibold mt-1">Auto-kompres ke golden ratio</div>
              </div>
              @endif
            </div>
            <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" class="absolute inset-0 opacity-0 cursor-pointer" onchange="previewPhoto(this)">
          </div>

          <p class="text-[11px] text-zinc-400 leading-relaxed px-2">
            Klik atau seret foto ke dalam kotak di atas. Foto otomatis disesuaikan agar landmark wajah tajam terbaca oleh mesin turnstile.
          </p>

          @if($u->photo)
          <div class="pt-2 border-t border-zinc-100 flex items-center justify-center">
            <label class="text-xs text-red-600 hover:text-red-700 cursor-pointer flex items-center gap-1.5 font-semibold">
              <input type="checkbox" name="remove_photo" value="1" class="h-4 w-4 rounded border-zinc-300 text-red-600 focus:ring-red-400" onchange="toggleRemove(this)"> Hapus Foto Saat Ini
            </label>
          </div>
          @endif
          @error('photo')<span class="text-xs text-red-600 block mt-1 font-semibold">{{ $message }}</span>@enderror
        </div>

        {{-- INFO KARTU / RFID BOX & STATUS CUTI/AKTIF --}}
        <div class="glass rounded-[28px] p-6 space-y-4">
          <div class="flex items-center justify-between border-b border-zinc-100 pb-3">
            <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Status Akses</span>
            <span id="badgeStatusAkses" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold {{ (int)old('is_leave', $u->is_leave ?? 0) === 1 ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200' }}">
              {{ (int)old('is_leave', $u->is_leave ?? 0) === 1 ? '🏖️ Cuti' : '⚡ Aktif' }}
            </span>
          </div>

          {{-- SLIDE TOGGLE BUTTON CUTI / AKTIF --}}
          <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-200/80 flex items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold text-zinc-800" id="labelSlideStatus">
                {{ (int)old('is_leave', $u->is_leave ?? 0) === 1 ? 'Sedang Cuti' : 'Personel Aktif' }}
              </div>
              <div class="text-[10px] text-zinc-400 mt-0.5" id="descSlideStatus">
                {{ (int)old('is_leave', $u->is_leave ?? 0) === 1 ? 'Akses gerbang nonaktif (eFlag: 0)' : 'Akses gerbang diizinkan (eFlag: 1)' }}
              </div>
            </div>

            {{-- Hidden input boolean is_leave --}}
            <input type="hidden" name="is_leave" id="isLeaveInput" value="{{ old('is_leave', $u->is_leave ?? 0) }}">

            <button type="button" id="toggleLeaveBtn" onclick="toggleStatusCuti()" class="relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ (int)old('is_leave', $u->is_leave ?? 0) === 1 ? 'bg-amber-500' : 'bg-emerald-500' }}">
              <span id="toggleKnob" class="pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out {{ (int)old('is_leave', $u->is_leave ?? 0) === 1 ? 'translate-x-6' : 'translate-x-0' }}"></span>
            </button>
          </div>

          <div class="space-y-2 text-xs">
            <div class="flex justify-between items-center py-1.5 border-b border-zinc-100">
              <span class="text-zinc-500">RFID Desimal</span>
              <span id="previewCardDec" class="font-mono font-bold text-fq-600 text-sm">{{ $u->Card ?: '—' }}</span>
            </div>
            <div class="flex justify-between items-center py-1.5 border-b border-zinc-100">
              <span class="text-zinc-500">Wiegand Hex</span>
              <span id="previewCardHex" class="font-mono font-bold text-amber-600 text-xs">{{ $u->Card && is_numeric($u->Card) ? strtoupper(str_pad(dechex((int)$u->Card),8,'0',STR_PAD_LEFT)) : '—' }}</span>
            </div>
            <div class="flex justify-between items-center py-1.5">
              <span class="text-zinc-500">Protokol Hardware</span>
              <span class="font-semibold text-zinc-700">TCP Socket :7788</span>
            </div>
          </div>
        </div>
      </div>

      {{-- KOLOM KANAN (8 KOLOM): DATA ISIAN FORM LENGKAP --}}
      <div class="lg:col-span-8 space-y-6">
        
        {{-- SECTION 1: IDENTITAS UTAMA --}}
        <div class="glass rounded-[28px] p-6 sm:p-8 space-y-5">
          <div class="border-b border-zinc-100 pb-3">
            <h3 class="font-space font-bold text-base text-zinc-900">1. Identitas Personel</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Informasi dasar nama, nomor induk kepegawaian, dan posisi kedinasan.</p>
          </div>

          <div class="space-y-4">
            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">
                Nama Lengkap <span class="text-red-500">*</span>
              </label>
              <input id="nameInput" name="NAME" value="{{ old('NAME',$u->NAME) }}" required maxlength="50" placeholder="Contoh: Letda Inf Ahmad Yani" class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-semibold focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
              @error('NAME')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">
                  ID Personel (EMPLOYEE_ID / NIK)
                </label>
                <input name="EMPLOYEE_ID" value="{{ old('EMPLOYEE_ID',$u->EMPLOYEE_ID) }}" maxlength="15" placeholder="Contoh: EMP-9001" class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
                <span class="text-[11px] text-zinc-400 mt-1 block">Kunci unik integrasi API</span>
                @error('EMPLOYEE_ID')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
              </div>

              <div>
                <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">
                  Tipe Pengguna <span class="text-red-500">*</span>
                </label>
                <select name="user_type" class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-semibold outline-none focus:border-fq-400">
                  <option value="1" {{ (string)old('user_type',$u->user_type ?? 1)==='1'?'selected':'' }}>1 - Member</option>
                  <option value="2" {{ (string)old('user_type',$u->user_type)==='2'?'selected':'' }}>2 - Karyawan</option>
                </select>
                @error('user_type')<span class="text-xs text-red-600 mt-1 block">{{ $message }}</span>@enderror
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">
                No. Identitas Nasional (KTP / Paspor)
              </label>
              <input id="noIdentitasInput" name="NoIdentitas" value="{{ old('NoIdentitas',$u->NoIdentitas) }}" maxlength="30" placeholder="Contoh: 3171234509900001" oninput="checkCardAndNikMatch()" class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-sm font-mono focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
              @error('NoIdentitas')<span class="text-xs text-red-600 mt-1 block font-semibold">{{ $message }}</span>@enderror
            </div>
          </div>
        </div>

        {{-- SECTION 2: KREDENSIAL AKSES & RFID --}}
        <div class="glass rounded-[28px] p-6 sm:p-8 space-y-5">
          <div class="border-b border-zinc-100 pb-3">
            <h3 class="font-space font-bold text-base text-zinc-900">2. Kartu Akses RFID & Turnstile</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Nomor kartu fisik yang akan ditap pada reader gerbang.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
            <div class="sm:col-span-9">
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">
                Nomor Kartu (RFID Desimal)
              </label>
              <input id="cardInput" name="Card" value="{{ old('Card',$u->Card) }}" maxlength="15" placeholder="Tempel kartu pada reader USB / ketik desimal..." oninput="updateCardPreview(); checkCardAndNikMatch();" class="w-full rounded-2xl border border-zinc-200 bg-white px-4 py-2.5 text-base font-mono font-bold text-zinc-800 focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
              <span class="text-[11px] text-zinc-400 mt-1 block">Dapat langsung membaca scanner kartu USB di PC</span>
              @error('Card')<span class="text-xs text-red-600 mt-1 block font-semibold">{{ $message }}</span>@enderror
              <span id="sameCardNikWarning" class="hidden text-xs text-red-600 mt-1.5 font-semibold bg-red-50 p-2 rounded-xl border border-red-200 block">
                ⚠️ Nomor Kartu (Card) tidak boleh sama dengan No. Identitas (NoIdentitas)!
              </span>
            </div>

            <div class="sm:col-span-3 pt-1">
              <button type="button" onclick="generateRandomCard()" class="w-full py-2.5 px-4 rounded-2xl bg-zinc-900 text-white text-xs font-bold hover:bg-black transition flex items-center justify-center gap-1.5 shadow-sm">
                <span>🎲</span> Buat Acak
              </button>
            </div>
          </div>
        </div>

        {{-- SECTION 3: PENEMPATAN & MASA BERLAKU --}}
        <div class="glass rounded-[28px] p-6 sm:p-8 space-y-5">
          <div class="border-b border-zinc-100 pb-3">
            <h3 class="font-space font-bold text-base text-zinc-900">3. Penempatan & Masa Berlaku Akses</h3>
            <p class="text-xs text-zinc-400 mt-0.5">Pengaturan zona gerbang cabang, departemen, serta batas tanggal aktif turnstile.</p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">Cabang (Branch)</label>
              <select name="Branchid" class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-semibold outline-none focus:border-fq-400">
                @foreach($branches as $b)
                <option value="{{ $b->id }}" {{ (string)old('Branchid',$u->Branchid)==(string)$b->id ? 'selected':'' }}>{{ $b->name }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">Departemen</label>
              <select name="Depid" class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2.5 text-sm font-semibold outline-none focus:border-fq-400">
                @foreach($departemens as $d)
                <option value="{{ $d->id }}" {{ (string)old('Depid',$u->Depid)==(string)$d->id ? 'selected':'' }}>{{ $d->name }}</option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">Mulai Berlaku (Begin)</label>
              <input name="BEGIN_DATE" type="datetime-local" value="{{ old('BEGIN_DATE', $u->BEGIN_DATE ? $u->BEGIN_DATE->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
            </div>

            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">Berakhir (End)</label>
              <input name="END_DATE" type="datetime-local" value="{{ old('END_DATE', $u->END_DATE ? $u->END_DATE->format('Y-m-d\TH:i') : '2079-12-31T23:59') }}" class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
            </div>

            <div>
              <label class="block text-xs font-bold tracking-widest uppercase text-zinc-500 mb-1.5">Tanggal Lahir (Opsional)</label>
              <input name="BIRTHDAY" type="date" value="{{ old('BIRTHDAY', $u->BIRTHDAY ? $u->BIRTHDAY->format('Y-m-d') : '') }}" class="w-full rounded-2xl border border-zinc-200 bg-white px-3 py-2 text-sm focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
            </div>
          </div>
        </div>

        {{-- ACTION BUTTONS --}}
        <div class="pt-2 flex flex-col sm:flex-row items-center gap-3">
          <button type="submit" class="w-full sm:flex-1 py-3.5 px-6 rounded-full bg-fq-600 text-white font-bold text-sm shadow-[0_8px_20px_rgba(220,38,38,.28)] hover:bg-fq-700 transition flex items-center justify-center gap-2">
            <span>💾</span> {{ $mode==='create'?'Simpan Data Personel':'Perbarui Data Personel' }}
          </button>
          <a href="{{ route('usersprofile.index') }}" class="w-full sm:w-auto px-8 py-3.5 rounded-full bg-white border border-zinc-200 text-zinc-700 font-bold text-sm hover:bg-zinc-50 transition text-center">
            Batal
          </a>
        </div>

      </div>
    </div>
  </form>
</div>

@push('scripts')
<script>
function toHex(card){
  if(!card) return '—';
  if(/^\d+$/.test(card)){
    try { return card.replace(/^0+/, '') ? BigInt(card).toString(16).toUpperCase().padStart(8,'0') : '00000000'; }
    catch(e){ const n=parseInt(card,10); return isNaN(n)? card.toUpperCase() : n.toString(16).toUpperCase().padStart(8,'0'); }
  }
  return card.toUpperCase();
}

function updateCardPreview(){
  const card = document.getElementById('cardInput').value.trim();
  const decEl = document.getElementById('previewCardDec');
  const hexEl = document.getElementById('previewCardHex');
  if(!card){
    decEl.textContent = '—';
    hexEl.textContent = '—';
    return;
  }
  decEl.textContent = card;
  hexEl.textContent = toHex(card);
}

function generateRandomCard(){
  const n = Math.floor(1000000000 + Math.random()*9000000000);
  document.getElementById('cardInput').value = String(n);
  updateCardPreview();
}

function previewPhoto(input){
  const file = input.files[0];
  if(!file) return;
  const badge = document.getElementById('photoSizeBadge');
  const sizeKB = (file.size/1024).toFixed(1);
  badge.textContent = `${sizeKB} KB (Original)`;
  badge.className = `text-[10px] font-mono ${file.size>100*1024?'text-amber-600 font-bold':'text-emerald-600 font-bold'}`;
  
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('photoImg');
    const pl = document.getElementById('photoPlaceholder');
    img.src = e.target.result;
    img.classList.remove('hidden');
    if(pl) pl.classList.add('hidden');
  };
  reader.readAsDataURL(file);
}

function toggleStatusCuti(){
  const input = document.getElementById('isLeaveInput');
  const btn = document.getElementById('toggleLeaveBtn');
  const knob = document.getElementById('toggleKnob');
  const badge = document.getElementById('badgeStatusAkses');
  const label = document.getElementById('labelSlideStatus');
  const desc = document.getElementById('descSlideStatus');

  const isLeave = parseInt(input.value || '0', 10) === 1;
  const nextLeave = isLeave ? 0 : 1;

  input.value = nextLeave;

  if(nextLeave === 1){
    // Menjadi Cuti
    btn.className = 'relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-amber-500';
    knob.className = 'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-6';
    badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700 border border-amber-200';
    badge.textContent = '🏖️ Cuti';
    label.textContent = 'Sedang Cuti';
    desc.textContent = 'Akses gerbang nonaktif (eFlag: 0)';
  } else {
    // Menjadi Aktif
    btn.className = 'relative inline-flex h-7 w-13 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-emerald-500';
    knob.className = 'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-0';
    badge.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200';
    badge.textContent = '⚡ Aktif';
    label.textContent = 'Personel Aktif';
    desc.textContent = 'Akses gerbang diizinkan (eFlag: 1)';
  }
}

function toggleRemove(cb){
  const img = document.getElementById('photoImg');
  if(cb.checked){
    if(img) img.classList.add('opacity-25', 'grayscale');
  } else {
    if(img) img.classList.remove('opacity-25', 'grayscale');
  }
}

function checkCardAndNikMatch(){
  const card = (document.getElementById('cardInput')?.value || '').trim();
  const nik = (document.getElementById('noIdentitasInput')?.value || '').trim();
  const warn = document.getElementById('sameCardNikWarning');
  if(!warn) return;

  if(card !== '' && nik !== '' && card === nik){
    warn.classList.remove('hidden');
  } else {
    warn.classList.add('hidden');
  }
}

document.addEventListener('DOMContentLoaded', () => {
  updateCardPreview();
  checkCardAndNikMatch();
});
</script>
@endpush
@endsection
