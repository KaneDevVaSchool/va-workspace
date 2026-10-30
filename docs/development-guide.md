# Máy mới

Repo Laravel + Vue (nwidart modules). PHP 8.3, Node, MySQL.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

Test PHP cần MySQL `va_workspace_db_test` (xem `phpunit.xml`). Test JS: `node --test tests/js`.

Quy tắc giao diện: [theme.md](./theme.md). Bẫy đang mở: [known-issues.md](./known-issues.md).
