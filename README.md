## BCP-HRIS Local Bootstrap

### Prasyarat

- PHP 8.2+
- MySQL aktif
- Database kosong atau database local yang ingin di-upgrade ke schema terbaru

### Konfigurasi `.env`

Set minimal nilai berikut:

```env
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bcp_nexus_hris
DB_USERNAME=root
DB_PASSWORD=
SESSION_DRIVER=file
```

### Bootstrap local

Jalankan urutan berikut:

```bash
composer install
php artisan migrate
php artisan db:seed
php artisan serve
```

### Akun default local

- Email: `admin@local.test`
- Password: `password`

### Catatan bootstrap

- Migration kompatibel dengan database legacy yang masih memakai `employee_payroll_settings` dan `payroll_items`.
- RBAC tidak lagi membuat tabel schema saat request berjalan; tabelnya harus tersedia dari migration.
- Seed minimum menyiapkan 1 company, 1 user `Super Admin`, dan master data employee dasar agar halaman login, dashboard, dan form employee bisa dibuka.
