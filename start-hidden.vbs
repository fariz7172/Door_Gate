' FitQuarters Gym — Hidden Launcher (VBScript)
' Jalan di background tanpa jendela CMD sama sekali (windowStyle 0)
' Dipanggil oleh start-app.bat via: wscript //nologo "start-hidden.vbs"

Option Explicit
Dim sh, projectDir, phpExe, servePort, serveHost
Set sh = CreateObject("WScript.Shell")

serveHost  = "127.0.0.1"
servePort  = "8090"

' Validasi — PORTABLE: deteksi folder otomatis dari lokasi VBS ini (bukan hardcode D:)
Dim fso
Set fso = CreateObject("Scripting.FileSystemObject")
projectDir = fso.GetParentFolderName(WScript.ScriptFullName)
' Hapus trailing \ jika ada
If Right(projectDir, 1) = "\" Then projectDir = Left(projectDir, Len(projectDir)-1)

' Cari php.exe: 1) C:\xampp\php\php.exe  2) dari PATH (where php)
Dim cand1, cand2
cand1 = "C:\xampp\php\php.exe"
cand2 = ""
On Error Resume Next
Dim tmpExec, tmpOut
Set tmpExec = sh.Exec("cmd /c where php 2>nul")
Do While tmpExec.Status = 0
  WScript.Sleep 100
Loop
If tmpExec.ExitCode = 0 Then cand2 = Trim(tmpExec.StdOut.ReadLine())
On Error GoTo 0

If fso.FileExists(cand1) Then
  phpExe = cand1
ElseIf cand2 <> "" And fso.FileExists(cand2) Then
  phpExe = cand2
ElseIf fso.FileExists("C:\php\php.exe") Then
  phpExe = "C:\php\php.exe"
Else
  phpExe = cand1 ' fallback untuk pesan error yang jelas
End If

If Not fso.FileExists(phpExe) Then
  MsgBox "PHP tidak ditemukan!" & vbCrLf & "Dicari di:" & vbCrLf & " - " & cand1 & vbCrLf & " - " & cand2 & vbCrLf & vbCrLf & "Install XAMPP atau tambahkan php ke PATH.", 16, "FitQuarters — Error"
  WScript.Quit 1
End If
If Not fso.FileExists(projectDir & "\artisan") Then
  MsgBox "artisan tidak ditemukan di:" & vbCrLf & projectDir & vbCrLf & vbCrLf & "Pastikan folder dicopy LENGKAP (file 'artisan' tanpa ekstensi harus ada)." & vbCrLf & "Cek Explorer: harus ada file 'artisan' 350 bytes di folder itu.", 16, "FitQuarters — Error"
  WScript.Quit 1
End If

' Hentikan proses lama di port yang sama (best effort, jangan gagalkan start)
On Error Resume Next
sh.Run "cmd /c for %P in (8090 7788 787) do @for /f ""tokens=5"" %a in ('netstat -aon ^| findstr /R /C:"":%P .*LISTENING""') do @taskkill /PID %a /T /F >nul 2>&1", 0, True
WScript.Sleep 800
On Error GoTo 0

' 1. Laravel Serve — hidden (0), tanpa file log (>nul 2>&1)
sh.Run "cmd /c cd /d """ & projectDir & """ && """ & phpExe & """ artisan serve --host=" & serveHost & " --port=" & servePort & " >nul 2>&1", 0, False

' 2. Gate Daemon — hidden (0), tanpa file log (>nul 2>&1)
sh.Run "cmd /c cd /d """ & projectDir & """ && """ & phpExe & """ artisan gate:serve >nul 2>&1", 0, False

' 3. Sync Users Daemon (loop setiap 5 menit) — hidden (0)
sh.Run "cmd /c cd /d """ & projectDir & """ && """ & phpExe & """ artisan users:sync-gate --loop >nul 2>&1", 0, False

' 4. Tunggu serve siap lalu buka browser
WScript.Sleep 3000
sh.Run "http://127.0.0.1:" & servePort & "/login", 1, False

' Notifikasi tray halus (pakai popup auto-close 4 detik, tanpa suara)
sh.Popup "Indonesia Defence System berjalan di background!" & vbCrLf & "Serve  : http://127.0.0.1:" & servePort & vbCrLf & "Gate   : Port 7788 + 787" & vbCrLf & "Sync   : Loop 5 Menit Aktif" & vbCrLf & vbCrLf & "Stop   : double-click stop-app.bat", 4, "Indonesia Defence System", 64
