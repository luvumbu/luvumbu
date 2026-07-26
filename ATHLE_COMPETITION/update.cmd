@echo off
REM Mise a jour complete : calendrier -> import -> fiches detaillees -> geocodage.
REM Usage : update.cmd            (12 mois a venir, Belgique)
REM         update.cmd --country=FR --months=6
REM
REM Pour sauter les fiches detaillees (long) : update.cmd --rapide

setlocal
set PHP=C:\xampp\php\php.exe
set ROOT=%~dp0
set ARGS=%*
set SKIPDETAILS=

echo %ARGS% | find "--rapide" >nul && set SKIPDETAILS=1
set ARGS=%ARGS:--rapide=%

echo === 1/4 Calendrier des competitions ===
pushd "%ROOT%scraper"
call node scrape.js %ARGS%
if errorlevel 1 goto :error
popd

echo.
echo === 2/4 Import en base ===
"%PHP%" "%ROOT%bin\import.php"
if errorlevel 1 goto :error

if defined SKIPDETAILS (
    echo.
    echo === 3/4 Fiches detaillees : ignore ^(--rapide^) ===
) else (
    echo.
    echo === 3/4 Fiches detaillees ^(epreuves, horaires^) ===
    pushd "%ROOT%scraper"
    call node details.js
    popd
    "%PHP%" "%ROOT%bin\import-details.php"
)

echo.
echo === 4/4 Geocodage des nouvelles villes ===
"%PHP%" "%ROOT%bin\geocode.php"
if errorlevel 1 goto :error

echo.
echo Termine. Ouvrez http://localhost/ATHLE_COMPETITION/
exit /b 0

:error
echo.
echo Echec a l'etape precedente.
exit /b 1
