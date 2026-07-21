# JIM Video Catcher — native helper uninstaller (Module 7)
$ErrorActionPreference = 'SilentlyContinue'
$HostName = 'com.jim.videocatcher'

Remove-Item -Path "HKCU:\Software\Microsoft\Edge\NativeMessagingHosts\$HostName" -Force
Remove-Item -Path "HKCU:\Software\Google\Chrome\NativeMessagingHosts\$HostName" -Force

Write-Host "Registry keys removed. The native helper is disabled." -ForegroundColor Green
Write-Host "You may now delete this native-host folder (including yt-dlp.exe / ffmpeg.exe)."
Read-Host "Press Enter to exit"
