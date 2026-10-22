<?php
require '/home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn/vendor/autoload.php';
$app = require '/home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$users = App\Models\User::query()
    ->whereNotNull('hrm_employee_uuid')
    ->where('hrm_employee_uuid', '!=', '')
    ->orderByDesc('id')
    ->limit(8)
    ->get(['id', 'email', 'hrm_employee_uuid']);
foreach ($users as $u) {
    echo $u->id.' '.$u->email.' uuid='.$u->hrm_employee_uuid.PHP_EOL;
}
