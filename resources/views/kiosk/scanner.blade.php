<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>Scanner — {{ $door->name }}</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;600;700&family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<script>tailwind.config={theme:{extend:{fontFamily:{jakarta:['Plus Jakarta Sans','sans-serif'],space:['Space Grotesk','sans-serif']},colors:{fq:{50:'#fef2f2',500:'#ef4444',600:'#dc2626',700:'#b91c1c',800:'#991b1b'}}}}}</script>
<style>
body { font-family:'Plus Jakarta Sans',sans-serif; margin:0; overflow:hidden; background:#000; color:#fff; }
#reader { width: 100vw; height: 100vh; position: absolute; z-index: 0; background: #111; }
#reader video { object-fit: cover !important; width: 100vw !important; height: 100vh !important; opacity: 0.6; }
.glass-overlay { position: absolute; inset: 0; z-index: 10; display: none; flex-direction: column; align-items: center; justify-content: center; text-align: center; }
.glass-box { backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); box-shadow: 0 8px 32px rgba(0,0,0,.3); border-radius: 32px; padding: 2.5rem; max-width: 90%; width: 400px; transform: translateY(20px); opacity: 0; transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
.glass-overlay.active { display: flex; }
.glass-overlay.active .glass-box { transform: translateY(0); opacity: 1; }

.granted { background: rgba(16,185,129,0.85); border: 1px solid rgba(255,255,255,0.4); }
.denied { background: rgba(220,38,38,0.85); border: 1px solid rgba(255,255,255,0.4); }

.scan-line { position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, transparent, #ef4444, transparent); box-shadow: 0 0 10px #ef4444; z-index: 5; animation: scan 2s infinite linear alternate; display: none; }
@keyframes scan { 0% { transform: translateY(20vh); } 100% { transform: translateY(80vh); } }

/* Idle UI overlay */
.idle-ui { position: absolute; inset: 0; z-index: 5; pointer-events: none; display: flex; flex-direction: column; justify-content: space-between; padding: 2rem; }
</style>
</head>
<body>

<div id="reader"></div>
<div class="scan-line" id="scanLine"></div>

<!-- Idle UI -->
<div class="idle-ui" id="idleUi">
  <div class="flex justify-between items-start">
    <div class="bg-black/40 backdrop-blur rounded-2xl px-4 py-2 border border-white/10">
      <div class="font-space font-bold tracking-tight">FitQuarters Gym</div>
      <div class="text-[10px] text-white/60 font-mono">{{ $door->door_code }}</div>
    </div>
    <div class="h-3 w-3 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,1)]"></div>
  </div>
  <div class="text-center mb-10">
    <div class="w-20 h-20 mx-auto border-4 border-white/20 rounded-3xl border-dashed animate-pulse"></div>
    <div class="mt-4 font-space font-bold text-2xl tracking-tight text-white drop-shadow-md">Tunjukkan Barcode</div>
    <div class="text-sm font-semibold text-white/70 drop-shadow mt-1">Ke arah kamera untuk membuka pintu</div>
  </div>
</div>

<!-- Result Overlay -->
<div class="glass-overlay" id="resultOverlay">
  <div class="glass-box" id="resultBox">
    <div id="resultIcon" class="text-7xl mb-4"></div>
    <div id="resultTitle" class="font-space font-bold text-3xl mb-1"></div>
    <div id="resultDesc" class="font-semibold text-white/90"></div>
  </div>
</div>

<script>
const doorCode = "{{ $door->door_code }}";
const direction = "{{ $door->direction === 'out' ? 'out' : 'in' }}";
let isScanning = true;

const beepGranted = new Audio('https://assets.mixkit.co/active_storage/sfx/2190/2190-preview.mp3');
const beepDenied = new Audio('https://assets.mixkit.co/active_storage/sfx/2951/2951-preview.mp3');

async function processScan(memberId){
    if(!isScanning) return;
    isScanning = false;
    document.getElementById('scanLine').style.display='none';
    document.getElementById('idleUi').style.display='none';

    try {
        const res = await fetch('/api/scan', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({door_code: doorCode, member_id: memberId, type: direction})
        });
        const data = await res.json();

        const overlay = document.getElementById('resultOverlay');
        const box = document.getElementById('resultBox');

        overlay.classList.add('active');
        if(data.status === 'granted'){
            box.className = 'glass-box granted';
            document.getElementById('resultIcon').innerText = '✅';
            document.getElementById('resultTitle').innerText = 'Akses Diberikan';
            document.getElementById('resultDesc').innerText = data.member_name ? `Halo, ${data.member_name}!` : 'Silakan masuk';
            beepGranted.play().catch(e=>console.log(e));
        } else {
            box.className = 'glass-box denied';
            document.getElementById('resultIcon').innerText = '🚫';
            document.getElementById('resultTitle').innerText = 'Akses Ditolak';
            document.getElementById('resultDesc').innerText = data.reason || 'Membership tidak valid';
            beepDenied.play().catch(e=>console.log(e));
        }
    } catch(e) {
        document.getElementById('resultOverlay').classList.add('active');
        const box = document.getElementById('resultBox');
        box.className = 'glass-box denied';
        document.getElementById('resultIcon').innerText = '⚠️';
        document.getElementById('resultTitle').innerText = 'Error Koneksi';
        document.getElementById('resultDesc').innerText = 'Hubungi resepsionis';
    }

    setTimeout(() => {
        document.getElementById('resultOverlay').classList.remove('active');
        document.getElementById('idleUi').style.display='flex';
        document.getElementById('scanLine').style.display='block';
        setTimeout(() => isScanning = true, 500);
    }, 3000);
}

const html5QrcodeScanner = new Html5Qrcode("reader");
html5QrcodeScanner.start(
    { facingMode: "environment" },
    { fps: 10, qrbox: {width: 250, height: 250} },
    (decodedText) => { processScan(decodedText); },
    (error) => { /* ignore */ }
).then(() => {
    document.getElementById('scanLine').style.display='block';
    // try to init audio policy bypass silently
    beepGranted.volume = 0; beepGranted.play().then(()=>beepGranted.pause()).catch(e=>{});
    beepGranted.volume = 1;
});
</script>
</body>
</html>
