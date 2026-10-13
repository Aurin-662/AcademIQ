@echo off
echo ===================================================
echo Starting AcademIQ Development Environment...
echo ===================================================

start "AcademIQ - Laravel" cmd /k "php artisan serve --port=8000"
start "AcademIQ - Fraud Microservice" cmd /k "cd fraud-service && .venv\Scripts\uvicorn main:app --port=8001"

echo.
echo AcademIQ services started successfully!
echo - Web Application:       http://127.0.0.1:8000
echo - Fraud ML Microservice: http://127.0.0.1:8001/docs
echo ===================================================
