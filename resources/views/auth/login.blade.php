<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<title>Login — Indonesia Defence System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&family=Plus+Jakarta+Sans:wght@500;700&family=Instrument+Serif&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{jakarta:['Plus Jakarta Sans','sans-serif'],space:['Space Grotesk','sans-serif'],serif:['Instrument Serif','serif']},colors:{fq:{50:'#fef2f2',100:'#fee2e2',200:'#fecaca',500:'#ef4444',600:'#dc2626',700:'#b91c1c',800:'#991b1b',900:'#7f1d1d'}}}}}</script>
<style>
*{font-family:'Plus Jakarta Sans',system-ui,-apple-system,Segoe UI,sans-serif}
.font-serif{font-display:swap}
.glass{backdrop-filter:blur(22px);-webkit-backdrop-filter:blur(22px);background:rgba(255,255,255,.86);border:1px solid rgba(255,255,255,.6);box-shadow:0 20px 60px rgba(220,38,38,.12),0 8px 32px rgba(0,0,0,.06)}
</style>
</head>
<body class="min-h-screen bg-[#fefefe] antialiased overflow-x-hidden">
{{-- mesh bg --}}
<div class="fixed inset-0 -z-10 bg-[#fefefe] overflow-hidden">
  <div class="absolute -top-32 -right-32 h-[700px] w-[700px] rounded-full" style="background:radial-gradient(ellipse at center, rgba(220,38,38,.08) 0%, transparent 70%)"></div>
  <div class="absolute -bottom-40 -left-32 h-[600px] w-[600px] rounded-full" style="background:radial-gradient(ellipse at center, rgba(239,68,68,.06) 0%, transparent 70%)"></div>
  <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 h-[900px] w-[1200px] rounded-full opacity-40" style="background:radial-gradient(ellipse at center, rgba(220,38,38,.04) 0%, transparent 65%)"></div>
</div>

<div class="min-h-screen flex">
  {{-- LEFT: Brand panel (hidden on mobile) --}}
  <div class="hidden lg:flex lg:w-[52%] relative overflow-hidden bg-gradient-to-br from-zinc-900 via-zinc-900 to-zinc-800">
    <div class="absolute inset-0 opacity-20" style="background:radial-gradient(ellipse 800px 600px at 30% 20%, rgba(220,38,38,.5), transparent 60%), radial-gradient(ellipse 600px 500px at 80% 85%, rgba(239,68,68,.3), transparent 60%)"></div>
    <div class="absolute top-0 right-0 w-[500px] h-[500px] rounded-full blur-3xl opacity-10" style="background:radial-gradient(circle, #ef4444, transparent 70%)"></div>
    <div class="relative z-10 flex flex-col justify-between p-10 xl:p-12 w-full">
      <div>
        <div class="flex items-center gap-3">
          <img src="{{ asset('logo.jpg') }}" alt="FitQuarters" class="h-12 w-12 object-contain rounded-2xl bg-white p-2 shadow-lg border border-white/20">
          <div>
            <div class="font-space font-bold text-white text-[18px] leading-none tracking-tight">Soyal <span class="text-red-400">Access</span></div>
            <div class="text-[11px] tracking-[0.18em] font-bold text-white/50 uppercase">Door Gateway</div>
          </div>
        </div>
        <div class="mt-14 xl:mt-16">
          <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/10 border border-white/15 text-white/80 text-xs font-semibold backdrop-blur">
            <span class="h-2 w-2 rounded-full bg-emerald-400 shadow-[0_0_8px_rgba(52,211,153,.8)] animate-pulse"></span>
            Sistem Akses Pintu Terintegrasi
          </div>
          <h1 class="mt-6 font-serif text-[42px] xl:text-[48px] leading-[0.95] tracking-tight text-white">
            Kelola<br>
            <span class="font-space font-bold text-red-400">Akses Gym</span><br>
            dengan Mudah
          </h1>
          <p class="mt-4 text-[15px] leading-relaxed text-white/60 max-w-[44ch]">Dashboard premium untuk sinkronisasi member, device gate & kontrol akses real-time ke mesin Tasoft / Soyal.</p>
          <div class="mt-8 grid grid-cols-3 gap-3 max-w-[420px]">
            <div class="rounded-2xl bg-white/10 border border-white/10 p-3 backdrop-blur">
              <div class="text-lg">🖧</div>
              <div class="text-xs font-bold text-white mt-1">Device Gate</div>
              <div class="text-[11px] text-white/50">Face · Soyal</div>
            </div>
            <div class="rounded-2xl bg-white/10 border border-white/10 p-3 backdrop-blur">
              <div class="text-lg">👥</div>
              <div class="text-xs font-bold text-white mt-1">Users Profile</div>
              <div class="text-[11px] text-white/50">Staff & Member</div>
            </div>
            <div class="rounded-2xl bg-white/10 border border-white/10 p-3 backdrop-blur">
              <div class="text-lg">📊</div>
              <div class="text-xs font-bold text-white mt-1">Monitoring</div>
              <div class="text-[11px] text-white/50">Log & Inside</div>
            </div>
          </div>
        </div>
      </div>
      <div class="text-xs text-white/30 font-medium">© {{ date('Y') }} FitQuarters Gym — Merah · Putih · Glass — bespoke</div>
    </div>
  </div>

  {{-- RIGHT: Login form --}}
  <div class="flex-1 flex items-center justify-center p-6 sm:p-8 lg:p-10 bg-[#fefefe] relative">
    {{-- mobile logo --}}
    <div class="lg:hidden absolute top-0 inset-x-0 h-36 bg-gradient-to-br from-zinc-900 to-zinc-800 overflow-hidden">
      <div class="absolute -top-10 -right-10 h-40 w-40 rounded-full opacity-20" style="background:radial-gradient(circle, #ef4444, transparent 70%)"></div>
      <div class="relative p-6 flex items-center gap-3">
        <img src="{{ asset('logo.png') }}" alt="FitQuarters" class="h-10 w-10 object-contain rounded-2xl bg-white p-1.5 shadow border border-white/20">
        <div>
          <div class="font-space font-bold text-white text-[16px] leading-none">Indonesia <span class="text-red-400">Defence</span></div>
          <div class="text-[10px] tracking-[0.16em] font-bold text-white/50 uppercase">Gate Gateway</div>
        </div>
      </div>
    </div>

    <div class="w-full max-w-[420px] mt-16 lg:mt-0">
      <div class="lg:hidden text-center mb-6">
        <h1 class="font-serif text-[28px] leading-none tracking-tight">Selamat <span class="font-space font-bold text-fq-600">Datang</span></h1>
        <p class="text-sm text-zinc-500 mt-1.5">Masuk untuk mengelola gateway</p>
      </div>
      <div class="hidden lg:block mb-8">
        <h2 class="font-serif text-[30px] leading-none tracking-tight">Masuk ke <span class="font-space font-bold text-fq-600">Dashboard</span></h2>
        <p class="text-sm text-zinc-500 mt-2">Gunakan akun admin untuk melanjutkan.</p>
      </div>

      @if($errors->any())
      <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-medium flex items-start gap-2">
        <span class="shrink-0 mt-0.5">⚠️</span><span>{{ $errors->first() }}</span>
      </div>
      @endif
      @if(session('ok'))
      <div class="mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 text-sm font-medium">{{ session('ok') }}</div>
      @endif

      <form method="POST" action="{{ route('login.post') }}" class="glass rounded-[28px] p-6 sm:p-7 space-y-5">
        @csrf
        <label class="block">
          <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Email</span>
          <div class="mt-1.5 relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">✉️</span>
            <input name="email" type="email" value="{{ old('email','admin@gmail.com') }}" required autofocus placeholder="admin@gmail.com"
              class="w-full rounded-2xl border border-zinc-200 bg-white pl-10 pr-4 py-3 text-sm font-medium focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition placeholder:text-zinc-400">
          </div>
        </label>
        <label class="block">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold tracking-widest uppercase text-zinc-500">Password</span>
            <span class="text-[11px] text-zinc-400">default: password</span>
          </div>
          <div class="mt-1.5 relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-zinc-400 text-sm">🔒</span>
            <input id="pwd" name="password" type="password" value="" required placeholder="••••••••"
              class="w-full rounded-2xl border border-zinc-200 bg-white pl-10 pr-11 py-3 text-sm font-medium focus:border-fq-400 focus:ring-4 focus:ring-red-100 outline-none transition">
            <button type="button" onclick="const i=document.getElementById('pwd'); i.type=i.type==='password'?'text':'password'; this.textContent=i.type==='password'?'👁️':'🙈'" class="absolute right-3 top-1/2 -translate-y-1/2 h-8 w-8 rounded-xl bg-zinc-50 border border-zinc-200 flex items-center justify-center text-xs hover:bg-white transition">👁️</button>
          </div>
        </label>

        <label class="flex items-center gap-2.5 cursor-pointer select-none">
          <input type="checkbox" name="remember" value="1" checked class="h-4 w-4 rounded border-zinc-300 text-fq-600 focus:ring-fq-500">
          <span class="text-sm font-semibold text-zinc-700">Ingat saya</span>
          <span class="ml-auto text-xs text-zinc-400">Stay signed in</span>
        </label>

        <button class="w-full py-3.5 rounded-full bg-fq-600 text-white font-bold shadow-[0_10px_24px_rgba(220,38,38,.32)] hover:bg-fq-700 active:scale-[0.99] transition flex items-center justify-center gap-2">
          Masuk — Buka Dashboard <span>→</span>
        </button>

        <div class="pt-2 flex items-center justify-center gap-2 text-[11px] text-zinc-400">
          <span class="h-2 w-2 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,.6)]"></span>
          Aman & Terenkripsi · Session Laravel
        </div>
      </form>

      <div class="mt-5 rounded-2xl bg-zinc-900 text-white px-4 py-3 flex items-center gap-3">
        <div class="h-9 w-9 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center text-sm shrink-0">💡</div>
        <div class="text-xs leading-relaxed">
          <span class="font-bold text-white">Kredensial demo:</span>
          <span class="font-mono text-white/80">admin@gmail.com</span> <span class="text-white/40">/</span> <span class="font-mono text-white/80">password</span>
        </div>
        <button type="button" onclick="navigator.clipboard.writeText('password'); this.textContent='✓'; setTimeout(()=>this.textContent='📋',1500)" class="ml-auto h-8 w-8 rounded-xl bg-white/10 border border-white/15 flex items-center justify-center text-xs hover:bg-white/15 transition shrink-0">📋</button>
      </div>

      <p class="mt-4 text-center text-xs text-zinc-400">Indonesia Defence System — Gate Access Control</p>
    </div>
  </div>
</div>
</body>
</html>
