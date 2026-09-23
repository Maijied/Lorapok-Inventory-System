# Lorapok-Inventory-System

## Installation Process

```bash
composer install
npm install && npm run dev
```

1. Create database (e.g. `bioxin_admin` or configure in your `.env` file)
2. Run migrations and database seeder:
```bash
php artisan migrate
php artisan migrate:refresh --seed
```
3. Start the application:
```bash
php artisan serve
```

### Default Admin Credentials
- **Email:** `admin@admin.com`
- **Password:** `12345678`
