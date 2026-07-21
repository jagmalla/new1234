@echo off
REM Native-messaging launcher for JIM Video Catcher.
REM Edge/Chrome invoke this .bat; it runs the Node host, passing the caller origin.
node "%~dp0host.mjs" %*
