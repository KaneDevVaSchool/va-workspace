<?php

declare(strict_types=1);

$root = '/home/vaschools-workspace/htdocs/workspace.vaschools.edu.vn';
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dryRun = in_array('--dry-run', $argv, true);

/** @var \Modules\Identity\App\Console\ReconcileHrmEmployeeUuidsCommand $cmd */
$cmd = $app->make(\Modules\Identity\App\Console\ReconcileHrmEmployeeUuidsCommand::class);
$input = new Symfony\Component\Console\Input\ArrayInput($dryRun ? ['--dry-run' => true] : []);
$output = new Symfony\Component\Console\Output\ConsoleOutput();
$cmd->setLaravel($app);
$code = $cmd->run($input, $output);
exit($code);
