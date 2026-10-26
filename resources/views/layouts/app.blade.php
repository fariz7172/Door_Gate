<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<title>@yield('title','Dashboard') — Soyal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{jakarta:['Plus Jakarta Sans','sans-serif'],space:['Space Grotesk','sans-serif'],serif:['Instrument Serif','serif']},colors:{fq:{50:'#fef2f2',100:'#fee2e2',200:'#fecaca',500:'#ef4444',600:'#dc2626',700:'#b91c1c',800:'#991b1b',900:'#7f1d1d'}}}}}</script>
<style>
*{font-family:'Plus Jakarta Sans',sans-serif}
.glass{backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);background:rgba(255,255,255,.82);border:1px solid rgba(220,38,38,.11);box-shadow:0 8px 32px rgba(220,38,38,.07),0 1px 2px rgba(0,0,0,.05)}
.glass-red{background:linear-gradient(135deg, rgba(220,38,38,.95), rgba(127,29,29,.92));border:1px solid rgba(255,255,255,.16)}
.mesh-bg{position:fixed;inset:0;z-index:-1;background:#fefefe;overflow:hidden}
.mesh-bg::before{content:'';position:absolute;width:900px;height:700px;background:radial-gradient(ellipse at center, rgba(220,38,38,.06) 0%, transparent 68%);top:-180px;right:-260px;border-radius:50%}
.mesh-bg::after{content:'';position:absolute;width:650px;height:650px;background:radial-gradient(ellipse at center, rgba(239,68,68,.045) 0%, transparent 70%);bottom:-140px;left:-120px;border-radius:50%}
/* scrollbar */
#sidebar::-webkit-scrollbar{width:4px}
#sidebar::-webkit-scrollbar-thumb{background:rgba(220,38,38,.18);border-radius:999px}
</style>
@stack('head')
</head>
<body class="min-h-screen bg-[#fefefe] text-zinc-900 antialiased">
<div class="mesh-bg"></div>

<!-- Sidebar Overlay (mobile) -->
<div id="sidebarOverlay" onclick="closeSidebar()" class="fixed inset-0 z-30 bg-zinc-900/30 backdrop-blur-[2px] hidden lg:hidden"></div>

<!-- ========== SIDEBAR ========== -->
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-[288px] bg-white border-r border-red-100/70 flex flex-col overflow-hidden -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-[cubic-bezier(.16,1,.3,1)]">
  <!-- Sidebar top — brand -->
  <div class="h-[64px] shrink-0 flex items-center gap-3 px-6 border-b border-red-100/60 relative overflow-hidden">
    <div class="absolute -top-10 -right-10 h-28 w-28 rounded-full bg-gradient-to-br from-red-100 to-transparent opacity-60 pointer-events-none"></div>
    <img src="{{ asset('logo.png') }}" alt="FitQuarters" class="h-10 w-10 object-contain rounded-2xl bg-white border border-red-100 p-1.5 shadow-sm relative">
    <div class="relative">
      <div class="font-space font-bold text-[15.5px] leading-none tracking-tight">Soyal <span class="text-fq-600">Access</span></div>
      <div class="text-[10.5px] tracking-[0.16em] font-bold text-zinc-400 uppercase">Door Gateway</div>
    </div>
    <button onclick="closeSidebar()" class="lg:hidden ml-auto h-8 w-8 rounded-full bg-zinc-100 flex items-center justify-center text-zinc-500">✕</button>
  </div>

  <!-- Nav -->
  <div class="flex-1 overflow-y-auto px-3 py-5 space-y-6">
    <div>
      <div class="px-3 mb-2.5 text-[10.5px] tracking-[0.16em] font-bold text-zinc-400 uppercase">Menu</div>
      <nav class="space-y-1">
        <a href="{{ route('dashboard') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('dashboard') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">◧</span>
          Dashboard
          @if(request()->routeIs('dashboard'))<span class="ml-auto h-2 w-2 rounded-full bg-white/80"></span>@endif
        </a>
        <a href="{{ route('devicegate.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('devicegate.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('devicegate.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">🖧</span>
          Device Gate
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full {{ request()->routeIs('devicegate.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ \Illuminate\Support\Facades\Cache::remember('cnt:devicegate',60,fn()=>\App\Models\DeviceGate::count()) }}</span>
        </a>
        <a href="{{ route('branch.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('branch.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('branch.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">🏢</span>
          Branch
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full {{ request()->routeIs('branch.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ \Illuminate\Support\Facades\Cache::remember('cnt:branch',60,fn()=>\App\Models\Branch::count()) }}</span>
        </a>
        <a href="{{ route('departemen.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('departemen.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('departemen.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">🗂️</span>
          Departemen
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full {{ request()->routeIs('departemen.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ \Illuminate\Support\Facades\Cache::remember('cnt:departemen',60,fn()=>\App\Models\Departemen::count()) }}</span>
        </a>
        <a href="{{ route('devicegroup.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('devicegroup.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('devicegroup.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">📑</span>
          Device Group
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full {{ request()->routeIs('devicegroup.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ \Illuminate\Support\Facades\Cache::remember('cnt:devicegroup',60,fn()=>\App\Models\DeviceGroup::count()) }}</span>
        </a>
        <a href="{{ route('usersprofile.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('usersprofile.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('usersprofile.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">👤</span>
          Users Profile
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full {{ request()->routeIs('usersprofile.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ \Illuminate\Support\Facades\Cache::remember('cnt:usersprofile',60,fn()=>\App\Models\UsersProfile::count()) }}</span>
        </a>
        <a href="{{ route('gate-logs.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('gate-logs.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('gate-logs.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">📡</span>
          Gate API Log
        </a>
        <!-- <a href="{{ route('endpoints.index') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('endpoints.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('endpoints.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">◎</span>
          Device Endpoints
          <span class="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full {{ request()->routeIs('endpoints.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500' }}">{{ \Illuminate\Support\Facades\Cache::remember('cnt:apiendpoint',60,fn()=>\App\Models\ApiEndpoint::count()) }}</span>
        </a> -->
        <!-- <a href="{{ route('settings.edit') }}" class="group flex items-center gap-3 px-3.5 py-[11px] rounded-2xl text-sm font-semibold transition {{ request()->routeIs('settings.*') ? 'bg-fq-600 text-white shadow-[0_8px_20px_rgba(220,38,38,.32)]' : 'text-zinc-600 hover:bg-red-50 hover:text-fq-700' }}">
          <span class="h-8 w-8 rounded-xl flex items-center justify-center shrink-0 text-[15px] {{ request()->routeIs('settings.*') ? 'bg-white/15 text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-white group-hover:text-fq-600' }}">⚙</span>
          Pengaturan
        </a> -->
        <!-- <a href="{{ route('doors.index') }}" class="group flex items-center gap-3 px-3 py-2 rounded-xl text-xs font-medium transition {{ request()->routeIs('doors.*') ? 'bg-zinc-900 text-white' : 'text-zinc-400 hover:bg-zinc-50 hover:text-zinc-600' }}">
          <span class="h-6 w-6 rounded-lg flex items-center justify-center shrink-0 text-[11px] {{ request()->routeIs('doors.*') ? 'bg-white/15' : 'bg-zinc-100' }}">⬢</span>
          Legacy Doors <span class="ml-auto opacity-60">deprecated</span>
        </a> -->
      </nav>
    </div>

  </div>

  <!-- Sidebar footer — status & user -->
  <div class="shrink-0 border-t border-red-100/60 p-3 space-y-3 bg-gradient-to-b from-white to-red-50/30">
    <div class="flex items-center gap-2.5 px-3 py-2.5 rounded-2xl bg-emerald-50 border border-emerald-200">
      <span class="h-2.5 w-2.5 rounded-full shrink-0 bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,.6)]"></span>
      <div class="min-w-0">
        <div class="text-xs font-bold leading-none text-emerald-800">Gate Server Active</div>
        <div class="text-[11px] text-zinc-500 leading-none truncate">Socket TCP :7788 &amp; :787</div>
      </div>
    </div>
    
    <div class="flex items-center justify-between gap-2 px-2 group">
      <div class="flex items-center gap-3 min-w-0">
        <div class="h-9 w-9 rounded-full bg-gradient-to-br from-fq-600 to-fq-800 flex items-center justify-center text-white font-bold text-sm shadow shrink-0">{{ substr(auth()->user()->name ?? 'A',0,1) }}</div>
        <div class="min-w-0">
          <div class="text-xs font-bold leading-none truncate text-zinc-800">{{ auth()->user()->name ?? 'Admin FitQuarters' }}</div>
          <div class="text-[11px] text-zinc-500 leading-none truncate mt-0.5">{{ auth()->user()->email ?? 'admin@fitquarters.id' }}</div>
        </div>
      </div>
      <form method="POST" action="{{ route('logout') }}" title="Keluar">
        @csrf
        <button class="h-8 w-8 rounded-full bg-white border border-red-200 hover:bg-red-50 text-red-600 flex items-center justify-center transition shadow-sm" title="Logout">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
        </button>
      </form>
    </div>
  </div>
</aside>

<!-- ========== MAIN ========== -->
<div class="lg:pl-[288px] min-h-screen flex flex-col">
  <!-- Topbar — NO nav links, only actions -->
  <header class="sticky top-0 z-20 h-[64px] glass border-b border-red-100/60 flex items-center gap-3 px-4 sm:px-6 lg:px-8">
    <button onclick="toggleSidebar()" class="lg:hidden h-9 w-9 rounded-xl bg-white border border-zinc-200 flex items-center justify-center text-zinc-600 hover:bg-zinc-50 shrink-0" aria-label="Menu">☰</button>
    <div class="min-w-0">
      <div class="font-space font-bold text-[15px] leading-none tracking-tight lg:hidden">Soyal <span class="text-fq-600">Access</span></div>
      <div class="hidden lg:block text-[11px] tracking-[0.16em] font-bold text-zinc-400 uppercase">@yield('breadcrumb','Dashboard')</div>
      <h1 class="hidden lg:block font-space font-bold text-[18px] leading-none tracking-tight mt-1">@yield('title','Dashboard')</h1>
      <div class="lg:hidden text-xs font-semibold text-zinc-500 leading-none mt-0.5">@yield('title','Dashboard')</div>
    </div>
    <div class="ml-auto flex items-center gap-2">
      <span class="hidden sm:inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full bg-white border border-zinc-200 text-zinc-600">
        <span class="h-2 w-2 rounded-full bg-emerald-500"></span> v1.0
      </span>
      <div class="hidden sm:flex h-9 w-9 rounded-full bg-gradient-to-br from-fq-600 to-fq-800 items-center justify-center text-white font-bold text-sm shadow lg:hidden">A</div>
    </div>
  </header>

  @if(session('ok'))
  <div class="px-4 sm:px-6 lg:px-8 pt-4">
    <div class="glass border-emerald-200 bg-emerald-50/80 text-emerald-800 px-4 py-3 rounded-2xl text-sm font-medium flex items-center gap-2">✓ {{ session('ok') }}</div>
  </div>
  @endif

  <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
    @yield('content')
  </main>

  <footer class="px-4 sm:px-6 lg:px-8 py-6 text-center text-xs text-zinc-400 border-t border-zinc-100/60">
    Soyal Door Gateway — <span class="font-semibold text-fq-600">Merah</span> · Putih · Power By Soyal
  </footer>
</div>

<script>
function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebarOverlay').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebarOverlay').classList.add('hidden');document.body.style.overflow='';}
function toggleSidebar(){const s=document.getElementById('sidebar');s.classList.contains('-translate-x-full')?openSidebar():closeSidebar();}
window.openSidebar=openSidebar;window.closeSidebar=closeSidebar;window.toggleSidebar=toggleSidebar;
</script>
@stack('scripts')
</body>
</html>
