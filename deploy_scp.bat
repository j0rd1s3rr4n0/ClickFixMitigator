@echo off
setlocal enabledelayedexpansion
set SERVER=parthenoun@clickfix.jordiserrano.me
set REMOTE_BASE=/home/parthenoun/ClickFix/Web/ClickFix
set LOCAL_BASE=C:\Users\kunakawi\Documents\GitHub\ClickFixMitigator\Web\ClickFix

echo === ClickFix SCP Deploy ===
echo Server: %SERVER%
echo Remote: %REMOTE_BASE%
echo.

set FILES=src\clickfix_core.php src\clickfix_llm.php src\clickfix_auto_investigation.php src\clickfix_blog_feed.php src\clickfix_seo.php src\clickfix_domain_feeds.php src\clickfix_socdefenders.php src\clickfix_abusech.php api\llm.php api\auto_investigation.php api\blog_feed.php api\domain_feeds.php scripts\worker.php scripts\fetch_all.php dashboard.php partials\dashboard_sidebar.php partials\dashboard_style.php partials\dashboard_scripts.php robots.txt .env.security.example

for %%f in (%FILES%) do (
    set LOCAL=%LOCAL_BASE%\%%f
    set REMOTE=%REMOTE_BASE%/%%f
    set REMOTE_DIR=%REMOTE_BASE%\
    for %%d in ("%%~dpf") do set REMOTE_DIR=%REMOTE_BASE%/%%~pd
    if "%%~xf"=="" set REMOTE_DIR=%REMOTE_BASE%
    set REMOTE_DIR=!REMOTE_DIR:\=/!
    set REMOTE_DIR=!REMOTE_DIR: =!
    if "!REMOTE_DIR:~-1!"=="/" set REMOTE_DIR=!REMOTE_DIR!
    echo [SCP] %%f
    scp -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o ServerAliveInterval=5 "!LOCAL!" "%SERVER%:!REMOTE_DIR!" 2>&1
    if !ERRORLEVEL! neq 0 echo   FAILED
)
echo.
echo === Done ===
