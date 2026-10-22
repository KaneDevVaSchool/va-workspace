<?php
require '/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn/vendor/autoload.php';
$app = require '/home/vaschools-hrm/htdocs/hrm.vaschools.edu.vn/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$punch = App\Models\AttendanceWorkspacePunch::query()->with('employee')->orderByDesc('id')->first();
if ($punch) {
    $emp = $punch->employee;
    echo 'latest punch emp_id='.$punch->employee_id.' uuid='.($emp->uuid ?? '?').PHP_EOL;
}
$exists = App\Models\Employee::query()->where('uuid', 'aa999990-fc51-45c8-9783-ad144b2db663')->exists();
echo 'test uuid exists='.($exists ? 'yes' : 'no').PHP_EOL;

$assign = Illuminate\Support\Facades\DB::table('employee_assignments')
    ->where('employee_id', 1)
    ->where('is_primary', 1)
    ->first(['org_unit_id', 'company_id']);
echo 'emp1 primary assignment: '.json_encode($assign).PHP_EOL;
