#!/bin/bash

set -e

echo "========================================="
echo "HRManager Backend - Setup Script"
echo "========================================="

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Copy environment file if not exists
if [ ! -f .env ]; then
    echo "📄 Creating .env file from .env.docker..."
    cp .env.docker .env
fi

# Build and start containers
echo "🐳 Building and starting Docker containers..."
docker-compose down -v 2>/dev/null || true
docker-compose up -d --build

# Wait for MySQL to be ready
echo "⏳ Waiting for MySQL to be ready..."
sleep 30

# Check if container is running
if ! docker-compose ps | grep -q "hrmanager-app.*Up"; then
    echo "❌ App container is not running. Check logs with: docker-compose logs app"
    exit 1
fi

echo ""
echo "========================================="
echo "✅ Setup completed successfully!"
echo "========================================="
echo ""
echo "🌐 Application URLs:"
echo "   API Base URL: http://localhost/api/v1"
echo "   Health Check: http://localhost/up"
echo ""
echo "🔐 Default Admin Credentials:"
echo "   Email: admin@hrmanager.local"
echo "   Password: Admin@2024!"
echo ""
echo "📚 Useful Commands:"
echo "   docker-compose logs -f app    # View app logs"
echo "   docker-compose exec app bash   # Enter container"
echo "   docker-compose exec app php artisan migrate:fresh --seed  # Reset database"
echo ""
echo "========================================="
