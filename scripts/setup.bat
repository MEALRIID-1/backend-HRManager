@echo off
chcp 65001 >nul
echo =========================================
echo HRManager Backend - Setup Script
echo =========================================
echo.

REM Check if Docker is installed
docker --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker is not installed. Please install Docker first.
    exit /b 1
)

docker-compose --version >nul 2>&1
if errorlevel 1 (
    echo ❌ Docker Compose is not installed. Please install Docker Compose first.
    exit /b 1
)

REM Copy environment file if not exists
if not exist .env (
    echo 📄 Creating .env file from .env.docker...
    copy .env.docker .env
)

REM Build and start containers
echo 🐳 Building and starting Docker containers...
docker-compose down -v 2>nul
docker-compose up -d --build

REM Wait for MySQL to be ready
echo ⏳ Waiting for MySQL to be ready...
timeout /t 30 /nobreak >nul

REM Check if container is running
docker-compose ps | findstr "hrmanager-app.*Up" >nul
if errorlevel 1 (
    echo ❌ App container is not running. Check logs with: docker-compose logs app
    exit /b 1
)

echo.
echo =========================================
echo ✅ Setup completed successfully!
echo =========================================
echo.
echo 🌐 Application URLs:
echo    API Base URL: http://localhost/api/v1
echo    Health Check: http://localhost/up
echo.
echo 🔐 Default Admin Credentials:
echo    Email: admin@hrmanager.local
echo    Password: Admin@2024!
echo.
echo 📚 Useful Commands:
echo    docker-compose logs -f app    # View app logs
echo    docker-compose exec app bash   # Enter container
echo    docker-compose exec app php artisan migrate:fresh --seed  # Reset database
echo.
echo =========================================
pause
