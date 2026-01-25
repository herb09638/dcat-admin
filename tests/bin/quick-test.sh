#!/usr/bin/env bash
#
# Quick local test - creates a Laravel app and installs dcat-admin
# Usage: ./tests/bin/quick-test.sh [laravel-version]
# Example: ./tests/bin/quick-test.sh 11.*
#

set -e

LARAVEL_VERSION="${1:-11.*}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TEST_APP="$PROJECT_ROOT/test-app"

echo "╔═══════════════════════════════════════════════════════╗"
echo "║         DCAT-ADMIN QUICK TEST                         ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""
echo "Laravel version: $LARAVEL_VERSION"
echo "Project root: $PROJECT_ROOT"
echo ""

# Cleanup
if [ -d "$TEST_APP" ]; then
    echo "[1/6] Removing existing test app..."
    rm -rf "$TEST_APP"
fi

# Create Laravel project
echo "[2/6] Creating Laravel $LARAVEL_VERSION project..."
cd "$PROJECT_ROOT"
composer create-project --prefer-dist --no-interaction laravel/laravel test-app "$LARAVEL_VERSION"

# Configure
echo "[3/6] Configuring package repository..."
cd "$TEST_APP"
composer config repositories.local path "$PROJECT_ROOT"

# Install dcat-admin
echo "[4/6] Installing dcat/laravel-admin..."
composer require "dcat/laravel-admin:*@dev" --no-interaction

# Setup environment
echo "[5/6] Setting up environment..."
cp .env.example .env
php artisan key:generate

# Use SQLite for simplicity
sed -i.bak 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
touch database/database.sqlite

# Install admin
echo "[6/6] Installing admin panel..."
php artisan admin:publish --force
php artisan migrate --force
php artisan admin:install --force 2>/dev/null || true

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║         INSTALLATION COMPLETE                         ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""
echo "Test app location: $TEST_APP"
echo ""
echo "To start the server:"
echo "  cd $TEST_APP && php artisan serve"
echo ""
echo "Then visit: http://127.0.0.1:8000/admin"
echo "Login: admin / admin"
echo ""
echo "To cleanup:"
echo "  rm -rf $TEST_APP"
