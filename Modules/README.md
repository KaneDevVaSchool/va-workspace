# Modules

Mỗi tính năng lớn của hệ thống là 1 module độc lập trong thư mục này,
quản lý bởi package `nwidart/laravel-modules`.

- Tạo module mới: `php artisan module:make TenModule` (sau khi đã cài package),
  hoặc copy thư mục `Example/` làm mẫu.
- Mỗi module tự chứa: routes (`web.php`, `api.php`, `manager.php`, `superadmin.php`),
  controllers, services, repositories, models, migrations, và Vue components/pages riêng.
- Kích hoạt/tắt module qua `modules_statuses.json` ở root.
- Xem `Modules/Example/README.md` để biết chi tiết cấu trúc & design pattern chuẩn.
