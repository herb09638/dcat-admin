# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Dcat Admin is a Laravel admin panel builder package that enables rapid development of admin dashboards with minimal code. It's a fork/evolution of `laravel-admin` with extensive improvements. The package provides Form, Grid, Show, and Tree builders for data-driven CRUD interfaces.

**Tech Stack:** PHP 8.0+, Laravel 9.x-12.x, Bootstrap 4, AdminLTE 3, jQuery 3

## Common Commands

```bash
# Install dependencies
composer install
npm install

# Asset compilation (Vite)
npm run dev          # Development build
npm run build        # Production JS build
npm run build:scss   # Compile SCSS files
npm run prod         # Full production build (JS + SCSS)

# Testing
composer test                    # Run PHPUnit tests
vendor/bin/phpunit              # Alternative
php artisan dusk                # Browser tests (Laravel Dusk)

# Static analysis
composer phpstan                # Run PHPStan

# Automated testing (creates test Laravel apps)
./tests/bin/quick-test.sh           # Quick test with Laravel 11
./tests/bin/quick-test.sh "12.*"    # Quick test with specific version
./tests/bin/test-upgrade.sh --all   # Full upgrade verification (all versions)

# When using this package in a Laravel app
php artisan admin:publish       # Publish assets
php artisan admin:install       # Install admin panel (creates tables, seeds admin user)
php artisan admin:make Post     # Generate CRUD scaffold for a model
```

## Architecture

### Core Patterns

**Fluent Builder Pattern** - All major components (Grid, Form, Show, Tree) use chainable method calls:
```php
Grid::make(new Repository())
    ->column('id')
    ->column('name')
    ->filter(function ($filter) { ... });
```

**Repository Pattern** - Data abstraction layer between controllers and database:
- Interface: `Dcat\Admin\Contracts\Repository`
- Implementations: `EloquentRepository`, `QueryBuilderRepository`
- Custom repositories can be created for non-Eloquent data sources

**Trait-Based Composition** - Functionality is split across focused traits in `Concerns/` directories:
- `Form/Concerns/` - Form field methods
- `Grid/Concerns/` - Grid configuration methods
- Each trait adds specific capabilities (HasAssets, HasPermissions, HasBuilderEvents)

**Section Injection** - WordPress-style filter system for injecting content into layouts (see `Admin::SECTION` constants in `src/Admin.php`)

### Key Directories

```
src/
├── Admin.php              # Main facade and static helpers
├── Form/                  # Form builder (60+ field types in Field/)
├── Grid/                  # Data grid builder
│   ├── Column/           # Column display and filtering
│   ├── Filter/           # Search filters (18+ types)
│   ├── Displayers/       # Value formatters
│   └── Actions/          # Row/batch actions
├── Show/                  # Detail page builder
├── Tree.php              # Hierarchical data builder
├── Repositories/         # Data access abstraction
├── Http/Controllers/     # Built-in controllers
├── Models/               # Built-in Eloquent models (Administrator, Role, Permission, Menu)
├── Widgets/              # UI components (Box, Card, Modal, Tab, etc.)
├── Console/              # Artisan commands
└── Support/helpers.php   # Global helper functions
```

### Request Flow

1. Middleware chain: `admin.app` → `admin.auth` → `admin.pjax` → `admin.bootstrap` → `admin.permission`
2. Controller receives request, creates Form/Grid/Show builder
3. Builder renders with data from Repository
4. PJAX middleware enables page transitions without full refresh

### Extension System

Extensions are service provider-based plugins stored in `admin_extensions` table:
```bash
php artisan admin:extension-make vendor/my-extension
php artisan admin:extension-install vendor/my-extension
```

## Key Files for Understanding the Codebase

- `src/AdminServiceProvider.php` - Package bootstrap and service registration
- `src/Admin.php` - Main facade with section constants and static helpers
- `src/Form.php` / `src/Grid.php` - Core builder implementations
- `config/admin.php` - All configuration options with documentation
- `src/Http/Controllers/AdminController.php` - Base controller pattern

## Database Tables

The package creates: `admin_users`, `admin_roles`, `admin_permissions`, `admin_menu`, `admin_settings`, `admin_extensions` (plus pivot tables)

## Namespace

All package code is under `Dcat\Admin\` namespace with PSR-4 autoloading from `src/`.

## Documentation

- Chinese: https://learnku.com/docs/dcat-admin
- English: http://www.dcatadmin.com/docs/en-2.x/quick-start.html
