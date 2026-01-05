@echo off
REM ═══════════════════════════════════════════════════════════════
REM  ClutchData Auto Sync - Windows Task Scheduler Launcher
REM  
REM  To schedule automatic sync:
REM  1. Open Task Scheduler (Win+R → taskschd.msc)
REM  2. Create Basic Task → Name: "ClutchData Sync"
REM  3. Trigger: Daily, repeat every 30 minutes
REM  4. Action: Start a program
REM  5. Program/script: D:\2DAW\wamp64\www\ClutchData\run_sync.bat
REM ═══════════════════════════════════════════════════════════════

cd /d "D:\2DAW\wamp64\www\ClutchData"

REM Try to use PHP from system PATH first
php auto_sync.php --quiet

REM If PHP is not in PATH, uncomment and modify the line below:
REM "D:\2DAW\wamp64\bin\php\php8.2.26\php.exe" auto_sync.php --quiet
