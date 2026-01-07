@echo off
REM ============================================
REM ClutchData - Scheduled Update Script
REM ============================================
REM This batch file runs the PHP update script
REM Schedule it with Windows Task Scheduler
REM
REM Recommended schedule: Every 30 minutes
REM ============================================

cd /d "C:\wamp64\www\ClutchData"
"C:\wamp64\bin\php\php8.4.0\php.exe" cron\update_matches.php >> logs\cron.log 2>&1

REM To set up in Windows Task Scheduler:
REM 1. Open Task Scheduler (taskschd.msc)
REM 2. Create Basic Task > Name: "ClutchData Update"
REM 3. Trigger: Daily, repeat every 30 minutes
REM 4. Action: Start a program
REM 5. Program: C:\wamp64\www\ClutchData\cron\run_update.bat
REM 6. Start in: C:\wamp64\www\ClutchData
