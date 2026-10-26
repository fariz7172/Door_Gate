@extends('layouts.app')
@section('title', 'Detail User')
@section('breadcrumb','Master / Users Profile / Detail')
@section('content')
<div class="w-full space-y-4">
  <a href="{{ route('usersprofile.index') }}" class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-zinc-400 hover:text-fq-600 transition">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
    Kembali ke Daftar Personel
  </a>

  <div class="glass rounded-[28px] overflow-hidden">
    {{-- Header --}}
    <div class="bg-gradient-to-br from-zinc-900 to-zinc-800 px-6 sm:px-8 py-6 flex gap-5 items-start relative overflow-hidden">
      <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full bg-gradient-to-br from-fq-600/20 to-transparent pointer-events-none"></div>
      <div class="h-20 w-20 sm:h-24 sm:w-24 rounded-[20px] bg-white/10 border border-white/20 overflow-hidden shrink-0 shadow-lg relative">
        @if($u->photo)
        <img src="{{ route('usersprofile.photo', $u) }}" class="h-full w-full object-cover">
        @else
        <div class="h-full w-full flex items-center justify-center text-white text-2xl font-bold">{{ strtoupper(substr($u->NAME,0,1)) }}</div>
        @endif
      </div>
      <div class="min-w-0 flex-1 relative">
        <div class="flex flex-wrap items-center gap-2">
          <h2 class="font-space font-bold text-xl sm:text-2xl text-white leading-none tracking-tight">{{ $u->NAME }}</h2>
          @if((int)$u->user_type===1)
            <span class="px-2.5 py-1 rounded-full bg-emerald-500 text-white text-[10px] font-bold tracking-widest uppercase">Member</span>
          @elseif((int)$u->user_type===2)
            <span class="px-2.5 py-1 rounded-full bg-blue-500 text-white text-[10px] font-bold tracking-widest uppercase">Karyawan</span>
          @else
            <span class="px-2.5 py-1 rounded-full bg-zinc-600 text-white text-[10px] font-bold tracking-widest uppercase">Tipe {{ $u->user_type }}</span>
          @endif
        </div>
        <div class="mt-2 flex flex-wrap gap-1.5">
          <span class="px-2.5 py-1 rounded-full bg-white/10 border border-white/15 text-white text-xs font-medium">🏢 {{ $u->branch->name ?? '—' }}</span>
          <span class="px-2.5 py-1 rounded-full bg-white/10 border border-white/15 text-white text-xs font-medium">🗂️ {{ $u->departemen->name ?? '—' }}</span>
          <span class="px-2.5 py-1 rounded-full bg-white/10 border border-white/15 text-white text-xs font-mono">ID #{{ $u->ID }}</span>
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
          <a href="{{ route('usersprofile.edit', $u) }}" class="px-4 py-1.5 rounded-full bg-white text-zinc-900 text-xs font-bold hover:bg-zinc-100 transition">✏️ Edit</a>
          <!-- @if($u->Card)
          <button type="button" onclick="downloadQrDetail()" class="px-4 py-1.5 rounded-full bg-fq-600 text-white text-xs font-bold hover:bg-fq-700 transition shadow">⬇ Download QR</button>
          @endif -->
        </div>
      </div>
    </div>

    {{-- Info grid --}}
    <div class="p-6 sm:p-8">
      <div class="grid sm:grid-cols-2 gap-6">
        <div class="space-y-4">
          <div>
            <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Identitas</div>
            <div class="mt-2 space-y-2 text-sm">
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">NIK / NoIdentitas</span><span class="font-mono font-bold text-zinc-800">{{ $u->NoIdentitas ?: '—' }}</span></div>
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">Tanggal Lahir</span><span class="font-semibold text-zinc-800">{{ $u->BIRTHDAY ? $u->BIRTHDAY->format('d M Y') : '—' }}</span></div>
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">ID Personel (NIK)</span><span class="font-mono font-semibold text-zinc-800">{{ $u->EMPLOYEE_ID ?: '—' }}</span></div>
            </div>
          </div>
          <div>
            <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Kartu Akses</div>
            <div class="mt-2 space-y-2 text-sm">
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">RFID Desimal</span><span class="font-mono font-bold text-fq-600">{{ $u->Card ?: '—' }}</span></div>
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">Hex (Wiegand)</span><span id="hexDetail" class="font-mono font-bold text-amber-600">{{ $u->Card && is_numeric($u->Card) ? strtoupper(str_pad(dechex((int)$u->Card),8,'0',STR_PAD_LEFT)) : ($u->Card ? strtoupper($u->Card) : '—') }}</span></div>
              <div class="flex justify-between gap-2 py-2"><span class="text-zinc-500">Masa Berlaku</span><span class="text-xs font-semibold text-zinc-700">{{ $u->BEGIN_DATE ? $u->BEGIN_DATE->format('d/m/Y H:i') : '—' }} <span class="text-zinc-400">s/d</span> {{ $u->END_DATE ? $u->END_DATE->format('d/m/Y H:i') : '—' }}</span></div>
            </div>
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Penempatan</div>
            <div class="mt-2 space-y-2 text-sm">
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">Branch</span><span class="font-semibold text-zinc-800">{{ $u->branch->name ?? '—' }} <span class="text-xs text-zinc-400 font-mono">#{{ $u->Branchid }}</span></span></div>
              <div class="flex justify-between gap-2 py-2 border-b border-zinc-100"><span class="text-zinc-500">Departemen</span><span class="font-semibold text-zinc-800">{{ $u->departemen->name ?? '—' }} <span class="text-xs text-zinc-400 font-mono">#{{ $u->Depid }}</span></span></div>
              <div class="flex justify-between gap-2 py-2"><span class="text-zinc-500">Tipe</span><span class="font-bold {{ $u->user_type===1?'text-emerald-600':($u->user_type===2?'text-amber-600':'text-blue-600') }}">{{ $u->user_type===0?'Staff/User':($u->user_type===1?'Member':'Personal Trainer') }}</span></div>
            </div>
          </div>

          {{-- QR Box — dari contoh_QR.txt --}}
          <!-- @if($u->Card)
          <div class="flex flex-col items-center gap-4 p-4 rounded-2xl bg-white border border-red-100 shadow-[0_8px_24px_rgba(220,38,38,.08)]">
            <div class="p-2.5 bg-white rounded-2xl shrink-0 shadow-lg border border-zinc-100">
              <img id="qrDetailImg" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($u->Card) }}&margin=0" alt="QR {{ $u->Card }}" class="w-36 h-36 object-contain">
            </div>
            <div class="space-y-1.5 text-xs text-center flex-1 min-w-0 w-full">
              <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 font-bold text-[11px]">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Kartu RFID & QR Siap Digunakan
              </div>
              <div class="text-zinc-700 font-medium">Desimal (RFID): <span class="font-mono font-bold text-fq-600 text-sm ml-1">{{ $u->Card }}</span></div>
              <div class="text-zinc-500 font-medium">Hex (Wiegand / Soyal): <span class="font-mono font-bold text-amber-600 text-sm ml-1" id="hexDetail2">{{ is_numeric($u->Card) ? strtoupper(str_pad(dechex((int)$u->Card),8,'0',STR_PAD_LEFT)) : strtoupper($u->Card) }}</span></div>
              <p class="text-[11px] text-zinc-400 leading-relaxed pt-1.5 border-t border-zinc-100">QR berisi nomor desimal kartu. Scan di <span class="font-semibold text-zinc-600">Scanner Gate</span> atau tap kartu fisik.</p>
              <button type="button" onclick="downloadQrDetail()" class="mt-1 inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-zinc-900 text-white text-xs font-bold hover:bg-black transition">⬇ Download QR JPG</button>
            </div>
          </div>
          @else
          <div class="p-6 rounded-2xl bg-amber-50 border border-amber-200 text-center">
            <div class="text-amber-700 font-bold text-sm">Belum ada kartu RFID</div>
            <div class="text-xs text-amber-600/80 mt-1">Tambahkan nomor kartu di Edit untuk generate QR.</div>
            <a href="{{ route('usersprofile.edit',$u) }}" class="mt-3 inline-flex px-4 py-1.5 rounded-full bg-amber-600 text-white text-xs font-bold hover:bg-amber-700">Isi Kartu</a>
          </div>
          @endif -->
        </div>
      </div>

      {{-- ================== TERDAFTAR DI DEVICE (UsersXDevice) ================== --}}
      <div class="mt-6 pt-5 border-t border-zinc-100">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
          <div class="text-[11px] tracking-[0.14em] font-bold text-zinc-400 uppercase">Terdaftar di Device <span class="normal-case tracking-normal text-zinc-500">— UsersXDevice ({{ $u->devices->count() }} mesin)</span></div>
          <span class="text-[11px] font-bold px-2.5 py-1 rounded-full {{ $u->devices->count() ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-zinc-100 text-zinc-500 border border-zinc-200' }}">{{ $u->devices->count() }} device</span>
        </div>

        @if($u->devices->count())
        <div class="rounded-2xl border border-zinc-200 overflow-hidden">
          <div class="overflow-auto">
            <table class="w-full text-sm">
              <thead class="bg-red-50/50 text-[11px] tracking-[0.14em] font-bold text-zinc-500 uppercase">
                <tr>
                  <th class="px-4 py-2.5 text-left">Device Gate</th>
                  <th class="px-4 py-2.5 text-left">SN / IP</th>
                  <th class="px-4 py-2.5 text-left">Group</th>
                  <th class="px-4 py-2.5 text-center">Status</th>
                  <th class="px-4 py-2.5 text-center">Lift</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-zinc-100">
                @foreach($u->devices as $pivot)
                @php $dg = $pivot->gate; @endphp
                <tr class="hover:bg-red-50/30 transition">
                  <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                      @if($dg)
                        <span class="h-2 w-2 rounded-full shrink-0 {{ $dg->is_connected ? 'bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,.5)]' : 'bg-red-400' }}"></span>
                        <div>
                          <div class="font-bold text-zinc-800 leading-tight">{{ $dg->name }}</div>
                          <div class="text-[11px] text-zinc-500">ID #{{ $dg->id }} · {{ $dg->type_label }} · {{ $dg->stat_label }}</div>
                        </div>
                      @else
                        <span class="h-2 w-2 rounded-full bg-zinc-300"></span>
                        <div>
                          <div class="font-bold text-zinc-400 italic">Device #{{ $pivot->gateId }} (dihapus)</div>
                          <div class="text-[11px] text-zinc-400">pivot id #{{ $pivot->id }}</div>
                        </div>
                      @endif
                    </div>
                  </td>
                  <td class="px-4 py-3 font-mono text-[11px] text-zinc-600">
                    @if($dg)
                      <div class="font-bold text-zinc-800">{{ $dg->sn ?: '—' }}</div>
                      <div class="opacity-70">{{ $dg->ip ?: '—' }}</div>
                    @else
                      <span class="text-zinc-400">—</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-[11px]">
                    @if($dg && $dg->group)
                      <span class="px-2 py-0.5 rounded-full bg-zinc-900 text-white font-bold text-[10px]">{{ $dg->group->name }}</span>
                      <span class="text-zinc-400 font-mono">#{{ $dg->groupid }}</span>
                    @elseif($dg)
                      <span class="text-zinc-400">—</span>
                    @else
                      <span class="text-zinc-400">—</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-center">
                    @if($dg)
                      <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $dg->is_connected ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200' }}">{{ $dg->is_connected ? 'Terhubung' : 'Putus' }}</span>
                    @else
                      <span class="px-2 py-0.5 rounded-full bg-zinc-100 text-zinc-400 text-[10px] font-bold">—</span>
                    @endif
                  </td>
                  <td class="px-4 py-3 text-center font-mono text-[11px] text-zinc-500">
                    {{ $pivot->Lift1 }}/{{ $pivot->Lift2 }}/{{ $pivot->Lift3 }}/{{ $pivot->Lift4 }}
                  </td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
          <div class="px-4 py-2.5 bg-zinc-50 border-t border-zinc-100 text-[11px] text-zinc-500">
            Data diambil dari <code class="bg-white border border-zinc-200 px-1 py-0.5 rounded">usersxdevice</code> where <code>userId = {{ $u->ID }}</code> — sinkron via modal <span class="font-semibold text-zinc-700">Kelola Mesin Device (#2 registerface + #3 useraccess)</span> di daftar.
          </div>
        </div>
        @else
        <div class="rounded-2xl border-2 border-dashed border-zinc-200 bg-zinc-50 p-6 text-center">
          <div class="text-zinc-400 text-2xl">🖧</div>
          <div class="font-bold text-zinc-700 mt-1">Belum terdaftar di device manapun</div>
          <div class="text-xs text-zinc-500 mt-1 max-w-[44ch] mx-auto">User ini belum pernah dikirim ke mesin. Centang user di daftar <a href="{{ route('usersprofile.index') }}" class="text-fq-600 font-bold hover:underline">Users Profile</a> lalu klik <span class="font-semibold text-zinc-800">Kelola Mesin Device → Kirim ke Mesin</span> untuk mendaftarkan.</div>
        </div>
        @endif
      </div>
      {{-- ================== END TERDAFTAR ================== --}}

    </div>
  </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
function downloadQrDetail(){
  const card = @json($u->Card);
  const name = @json($u->NAME);
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
        const a=document.createElement('a'); a.href=url; a.download=( (name||'User').replace(/\s+/g,'_')+'_QRCODE.jpg'); document.body.appendChild(a); a.click(); a.remove();
      }
      tempDiv.remove();
    },600);
  }catch(e){ console.error(e); tempDiv.remove(); }
}
</script>
@endpush
@endsection
