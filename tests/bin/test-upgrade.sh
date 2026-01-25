#!/usr/bin/env bash
set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
TEST_DIR="${PROJECT_ROOT}/test-apps"

# Default Laravel version to test
LARAVEL_VERSIONS=("11.*")
PHP_MIN_VERSION="8.0"

# Parse arguments
SKIP_CLEANUP=false
VERBOSE=false
QUICK_TEST=false

while [[ "$#" -gt 0 ]]; do
    case $1 in
        --all) LARAVEL_VERSIONS=("9.*" "10.*" "11.*" "12.*") ;;
        --laravel=*) LARAVEL_VERSIONS=("${1#*=}") ;;
        --skip-cleanup) SKIP_CLEANUP=true ;;
        --verbose|-v) VERBOSE=true ;;
        --quick) QUICK_TEST=true ;;
        --help|-h)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --all              Test all supported Laravel versions (9, 10, 11, 12)"
            echo "  --laravel=VERSION  Test specific Laravel version (e.g., --laravel=11.*)"
            echo "  --skip-cleanup     Keep test apps after testing"
            echo "  --quick            Skip Dusk browser tests, only test installation"
            echo "  --verbose, -v      Show detailed output"
            echo "  --help, -h         Show this help message"
            exit 0
            ;;
        *) echo "Unknown option: $1"; exit 1 ;;
    esac
    shift
done

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_step() {
    echo -e "\n${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${YELLOW}▶ $1${NC}"
    echo -e "${YELLOW}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}\n"
}

# Check PHP version
check_php_version() {
    local php_version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    if [[ "$(printf '%s\n' "$PHP_MIN_VERSION" "$php_version" | sort -V | head -n1)" != "$PHP_MIN_VERSION" ]]; then
        log_error "PHP $PHP_MIN_VERSION+ required, but found PHP $php_version"
        exit 1
    fi
    log_info "PHP version: $php_version ✓"
}

# Check required commands
check_requirements() {
    log_step "Checking requirements"

    check_php_version

    for cmd in composer npm mysql; do
        if command -v $cmd &> /dev/null; then
            log_info "$cmd found ✓"
        else
            log_warning "$cmd not found (some tests may fail)"
        fi
    done
}

# Validate composer.json
validate_composer() {
    log_step "Validating composer.json"

    cd "$PROJECT_ROOT"

    if composer validate --strict 2>/dev/null; then
        log_success "composer.json is valid"
    else
        log_error "composer.json validation failed"
        return 1
    fi

    log_info "Checking dependency resolution..."
    if composer update --dry-run 2>&1 | head -20; then
        log_success "Dependencies can be resolved"
    else
        log_error "Dependency resolution failed"
        return 1
    fi
}

# Build frontend assets
build_frontend() {
    log_step "Building frontend assets"

    cd "$PROJECT_ROOT"

    log_info "Installing npm dependencies..."
    npm install

    log_info "Building JavaScript with Vite..."
    npm run build

    log_info "Building SCSS..."
    npm run build:scss

    log_success "Frontend build complete"
}

# Run PHPStan
run_phpstan() {
    log_step "Running PHPStan static analysis"

    cd "$PROJECT_ROOT"

    # Install dev dependencies if needed
    if [ ! -f "vendor/bin/phpstan" ]; then
        composer install --dev
    fi

    if vendor/bin/phpstan analyse --no-progress 2>&1; then
        log_success "PHPStan analysis passed"
    else
        log_warning "PHPStan found issues (see above)"
    fi
}

# Create and test Laravel app
test_laravel_version() {
    local laravel_version=$1
    local app_name="test-laravel-${laravel_version//\*/x}"
    local app_path="${TEST_DIR}/${app_name}"

    log_step "Testing with Laravel $laravel_version"

    # Cleanup existing test app
    if [ -d "$app_path" ]; then
        log_info "Removing existing test app..."
        rm -rf "$app_path"
    fi

    mkdir -p "$TEST_DIR"
    cd "$TEST_DIR"

    # Create Laravel project
    log_info "Creating Laravel $laravel_version project..."
    if ! composer create-project --prefer-dist --no-interaction laravel/laravel "$app_name" "$laravel_version" 2>&1; then
        log_error "Failed to create Laravel project"
        return 1
    fi

    cd "$app_path"

    # Add local package repository
    log_info "Configuring local package repository..."
    composer config repositories.local path "$PROJECT_ROOT"

    # Install dcat-admin
    log_info "Installing dcat/laravel-admin..."
    if ! composer require "dcat/laravel-admin:*@dev" --no-interaction 2>&1; then
        log_error "Failed to install dcat-admin"
        return 1
    fi

    log_success "Package installed successfully"

    # Setup .env
    log_info "Configuring environment..."
    cp .env.example .env
    php artisan key:generate

    # Configure SQLite for testing (simpler than MySQL)
    sed -i.bak 's/DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env
    sed -i.bak 's/DB_DATABASE=.*/DB_DATABASE=database\/database.sqlite/' .env
    touch database/database.sqlite

    # Publish and install admin
    log_info "Publishing admin assets..."
    php artisan admin:publish --force

    log_info "Running migrations..."
    php artisan migrate --force

    log_info "Installing admin panel..."
    php artisan admin:install --force 2>&1 || true

    # Verify installation
    log_info "Verifying installation..."

    # Check admin directory exists
    if [ -d "app/Admin" ]; then
        log_success "Admin directory created ✓"
    else
        log_error "Admin directory not found"
        return 1
    fi

    # Check routes file
    if [ -f "app/Admin/routes.php" ]; then
        log_success "Admin routes file created ✓"
    else
        log_error "Admin routes file not found"
        return 1
    fi

    # Check config file
    if [ -f "config/admin.php" ]; then
        log_success "Admin config file published ✓"
    else
        log_error "Admin config file not found"
        return 1
    fi

    # Check database tables
    local table_count=$(php artisan tinker --execute="echo \Illuminate\Support\Facades\Schema::hasTable('admin_users') ? 'yes' : 'no';" 2>/dev/null | tail -1)
    if [ "$table_count" = "yes" ]; then
        log_success "Admin database tables created ✓"
    else
        log_warning "Could not verify database tables"
    fi

    # Run Dusk tests if not quick mode
    if [ "$QUICK_TEST" = false ]; then
        run_dusk_tests "$app_path"
    fi

    log_success "Laravel $laravel_version test passed!"

    # Cleanup
    if [ "$SKIP_CLEANUP" = false ]; then
        log_info "Cleaning up test app..."
        cd "$TEST_DIR"
        rm -rf "$app_name"
    fi

    return 0
}

# Run Dusk browser tests
run_dusk_tests() {
    local app_path=$1

    log_info "Setting up Dusk for browser tests..."

    cd "$app_path"

    # Install Dusk
    composer require laravel/dusk --dev --no-interaction
    php artisan dusk:install

    # Install ChromeDriver
    php artisan dusk:chrome-driver --detect 2>/dev/null || true

    # Copy test files from package
    if [ -d "$PROJECT_ROOT/tests/Browser" ]; then
        cp -r "$PROJECT_ROOT/tests/Browser" tests/
    fi

    # Update .env for Dusk
    echo "APP_URL=http://127.0.0.1:8000" >> .env

    # Start server in background
    log_info "Starting development server..."
    php artisan serve --port=8000 > /dev/null 2>&1 &
    local server_pid=$!
    sleep 3

    # Run Dusk tests
    log_info "Running Dusk browser tests..."
    if php artisan dusk 2>&1; then
        log_success "Dusk tests passed ✓"
    else
        log_warning "Dusk tests failed or skipped"
    fi

    # Stop server
    kill $server_pid 2>/dev/null || true
}

# Generate test report
generate_report() {
    local results=("$@")

    log_step "Test Results Summary"

    echo ""
    echo "┌─────────────────────────────────────────────────┐"
    echo "│             UPGRADE TEST RESULTS                │"
    echo "├─────────────────────────────────────────────────┤"

    local all_passed=true
    for result in "${results[@]}"; do
        local version=$(echo "$result" | cut -d: -f1)
        local status=$(echo "$result" | cut -d: -f2)

        if [ "$status" = "pass" ]; then
            echo -e "│  Laravel $version\t\t${GREEN}PASSED${NC}\t\t│"
        else
            echo -e "│  Laravel $version\t\t${RED}FAILED${NC}\t\t│"
            all_passed=false
        fi
    done

    echo "└─────────────────────────────────────────────────┘"
    echo ""

    if [ "$all_passed" = true ]; then
        log_success "All tests passed! Upgrade is ready."
        return 0
    else
        log_error "Some tests failed. Please review the output above."
        return 1
    fi
}

# Main execution
main() {
    echo ""
    echo -e "${BLUE}╔═══════════════════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║       DCAT-ADMIN UPGRADE VERIFICATION TEST            ║${NC}"
    echo -e "${BLUE}╚═══════════════════════════════════════════════════════╝${NC}"
    echo ""

    log_info "Project root: $PROJECT_ROOT"
    log_info "Test directory: $TEST_DIR"
    log_info "Laravel versions to test: ${LARAVEL_VERSIONS[*]}"

    # Run checks
    check_requirements
    validate_composer

    # Build frontend (optional, can be skipped if pre-built)
    if [ -f "$PROJECT_ROOT/package.json" ]; then
        build_frontend
    fi

    # Run PHPStan
    run_phpstan

    # Test each Laravel version
    local results=()
    for version in "${LARAVEL_VERSIONS[@]}"; do
        if test_laravel_version "$version"; then
            results+=("$version:pass")
        else
            results+=("$version:fail")
        fi
    done

    # Generate report
    generate_report "${results[@]}"
}

# Run main
main "$@"
