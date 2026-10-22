<?php
require '/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn/vendor/autoload.php';
$app = require '/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$e = App\Models\Employee::query()->find(1);
if ($e) {
    echo 'id=1 uuid='.$e->uuid.' code='.$e->employee_code.PHP_EOL;
}
$ou39 = App\Models\Employee::query()
    ->whereHas('primaryAssignment', fn ($q) => $q->where('org_unit_id', 39))
    ->limit(5)
    ->get(['id', 'uuid', 'employee_code']);
echo 'org39 count sample:'.PHP_EOL;
foreach ($ou39 as $row) {
    $p = App\Models\AttendanceWorkspacePunch::query()
        ->where('employee_id', $row->id)
        ->whereDate('work_date', '2026-10-22')
        ->first();
    echo $row->id.' '.$row->uuid.' punch='.($p ? ($p->check_in_time.'/'.$p->check_out_time) : 'none').PHP_EOL;
}
