# JIM Video Catcher — native helper installer (Module 7)
# Run in PowerShell:  Right-click this file > "Run with PowerShell"
# (If blocked:  powershell -ExecutionPolicy Bypass -File .\install-host.ps1)

$ErrorActionPreference = 'Stop'
$HostName = 'com.jim.videocatcher'
$Here = Split-Path -Parent $MyInvocation.MyCommand.Path

Write-Host "== JIM Video Catcher native helper installer ==" -ForegroundColor Cyan

# 1. Check Node.js is available (the host runs on Node).
try {
  $nodeV = (node -v) 2>$null
  Write-Host "Node.js found: $nodeV"
} catch {
  Write-Host "ERROR: Node.js is not installed or not on PATH." -ForegroundColor Red
  Write-Host "Install it from https://nodejs.org (LTS), reopen PowerShell, and re-run this."
  Read-Host "Press Enter to exit"; exit 1
}

# 2. Download yt-dlp.exe next to the host (skip if already present).
$ytdlp = Join-Path $Here 'yt-dlp.exe'
if (Test-Path $ytdlp) {
  Write-Host "yt-dlp.exe already present."
} else {
  Write-Host "Downloading yt-dlp.exe ..."
  Invoke-WebRequest -Uri 'https://github.com/yt-dlp/yt-dlp/releases/latest/download/yt-dlp.exe' -OutFile $ytdlp
  Write-Host "yt-dlp.exe downloaded."
}

# 3. (Optional) ffmpeg — needed to merge separate video+audio and for MP3.
$ffmpeg = Join-Path $Here 'ffmpeg.exe'
if (Test-Path $ffmpeg) {
  Write-Host "ffmpeg.exe already present."
} else {
  Write-Host "Downloading ffmpeg (this one is larger) ..."
  try {
    $zip = Join-Path $env:TEMP 'ffmpeg-jim.zip'
    Invoke-WebRequest -Uri 'https://github.com/GyanD/codexffmpeg/releases/latest/download/ffmpeg-release-essentials.zip' -OutFile $zip
    $tmp = Join-Path $env:TEMP 'ffmpeg-jim'
    if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
    Expand-Archive $zip -DestinationPath $tmp -Force
    $exe = Get-ChildItem $tmp -Recurse -Filter 'ffmpeg.exe' | Select-Object -First 1
    Copy-Item $exe.FullName $ffmpeg -Force
    Remove-Item $zip -Force; Remove-Item $tmp -Recurse -Force
    Write-Host "ffmpeg.exe installed."
  } catch {
    Write-Host "WARNING: ffmpeg download failed. MP3 and some merges may not work." -ForegroundColor Yellow
  }
}

# 4. Write the native-messaging manifest with the real .bat path.
$batPath = (Join-Path $Here 'host.bat')
$manifestSrc = Join-Path $Here 'com.jim.videocatcher.json'
$manifest = Get-Content $manifestSrc -Raw
$manifest = $manifest -replace '__HOST_BAT_PATH__', ($batPath -replace '\\', '\\')
$manifestOut = Join-Path $Here 'com.jim.videocatcher.installed.json'
Set-Content -Path $manifestOut -Value $manifest -Encoding UTF8
Write-Host "Wrote manifest: $manifestOut"

# 5. Register it for Edge (and Chrome, harmless if unused) under HKCU.
$edgeKey = "HKCU:\Software\Microsoft\Edge\NativeMessagingHosts\$HostName"
New-Item -Path $edgeKey -Force | Out-Null
Set-ItemProperty -Path $edgeKey -Name '(default)' -Value $manifestOut
Write-Host "Registered for Edge: $edgeKey"

$chromeKey = "HKCU:\Software\Google\Chrome\NativeMessagingHosts\$HostName"
New-Item -Path $chromeKey -Force | Out-Null
Set-ItemProperty -Path $chromeKey -Name '(default)' -Value $manifestOut
Write-Host "Registered for Chrome: $chromeKey"

Write-Host ""
Write-Host "DONE. Fully quit Edge (all windows) and reopen it." -ForegroundColor Green
Write-Host "Then open the JIM popup - it should show 'Native helper: on'."
Read-Host "Press Enter to exit"
