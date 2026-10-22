<?php
require '/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn/vendor/autoload.php';
$app = require '/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'punches=' . App\Models\AttendanceWorkspacePunch::count() . PHP_EOL;
$latest = App\Models\AttendanceWorkspacePunch::query()->orderByDesc('id')->limit(3)->get(['employee_id','work_date','check_in_time','check_out_time']);
foreach ($latest as $r) {
    echo $r->work_date.' emp='.$r->employee_id.' in='.$r->check_in_time.' out='.$r->check_out_time.PHP_EOL;
}
