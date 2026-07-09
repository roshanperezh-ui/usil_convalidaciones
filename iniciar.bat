@echo off
REM ============================================================
REM  Lanzador local - Sistema de Convalidaciones USIL
REM  Arranca el servidor y abre el navegador en /login
REM ============================================================
setlocal
cd /d "%~dp0"

where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] No se encontro "php" en el PATH.
    echo Abre una terminal donde funcione "php --version" y vuelve a intentar.
    pause
    exit /b 1
)

if not exist vendor\autoload.php (
    echo [ERROR] Falta la carpeta vendor. Ejecuta primero: composer install
    pause
    exit /b 1
)

echo Iniciando Convalidaciones USIL en http://127.0.0.1:8080 ...
echo (Cierra esta ventana o pulsa Ctrl+C para detener el servidor)

REM Abre el navegador tras un breve retardo, en segundo plano
start "" cmd /c "timeout /t 3 >nul & start "" http://127.0.0.1:8080/login"

php artisan serve --host=127.0.0.1 --port=8080

endlocal
