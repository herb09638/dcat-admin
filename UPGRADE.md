# Upgrade Guide

## Upgrading to 3.0 from 2.x

### PHP Version Requirements

**dcat-admin 3.0 requires PHP 8.0 or higher.**

### Laravel Version Requirements

**dcat-admin 3.0 requires Laravel 9.0 or higher.**

Support for Laravel 5.5, 6.x, 7.x, and 8.x has been dropped. If you are using an older Laravel version, please upgrade Laravel first before upgrading dcat-admin.

### Updating Dependencies

Update your `composer.json` file:

```json
{
    "require": {
        "dcat/laravel-admin": "^3.0"
    }
}
```

Then run:

```bash
composer update dcat/laravel-admin
```

### Breaking Changes

#### 1. Removed Support for Older Laravel Versions

The following Laravel version-specific code paths have been removed:

- Laravel 5.x `getForeignKey()` method (use `getForeignKeyName()` instead)
- Laravel 6.x `trans()` method (use `get()` instead)
- Laravel 7.x DateTimeFormatter trait conditional loading
- Laravel 8.x `seeds` directory (use `seeders` instead)

#### 2. Build System Changed to Vite

The frontend build system has been migrated from Laravel Mix to Vite.

**Old commands (Mix):**
```bash
npm run dev
npm run watch
npm run prod
```

**New commands (Vite):**
```bash
npm run dev          # Development build
npm run build        # Production JS build
npm run build:scss   # Compile SCSS files
npm run prod         # Full production build
```

If you have customized the build process, you will need to update your configuration to use `vite.config.js` instead of `webpack.mix.js`.

#### 3. Test Dependencies Updated

- `fzaninotto/faker` has been replaced with `fakerphp/faker`
- PHPUnit configuration updated for PHPUnit 10/11 compatibility
- PHPStan upgraded to version 2.x

### Migration Steps

1. **Backup your project** before upgrading.

2. **Update PHP** to version 8.0 or higher.

3. **Update Laravel** to version 9.0 or higher.

4. **Update dcat-admin**:
   ```bash
   composer require dcat/laravel-admin:^3.0
   ```

5. **Republish assets**:
   ```bash
   php artisan admin:publish --force
   ```

6. **Clear caches**:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```

7. **Update npm dependencies** (if building assets locally):
   ```bash
   rm -rf node_modules package-lock.json
   npm install
   ```

### Deprecated Features

The following features have been deprecated and will be removed in a future version:

- None at this time.

### New Features

- Full support for Laravel 12.x
- Improved build performance with Vite
- Updated PHPStan analysis with stricter type checking
