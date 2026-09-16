<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Storage;

try {
    $path = 'social/_healthcheck/test-'.time().'.txt';
    Storage::disk('s3')->put($path, 'ok');
    $exists = Storage::disk('s3')->exists($path);
    $url = Storage::disk('s3')->url($path);
    echo "PUT_OK=".($exists ? 'yes' : 'no')."\n";
    echo "URL=".$url."\n";
    Storage::disk('s3')->delete($path);
    echo "DELETE_OK=".(! Storage::disk('s3')->exists($path) ? 'yes' : 'no')."\n";
} catch (\Throwable $e) {
    echo "ERROR: ".get_class($e).": ".$e->getMessage()."\n";
}
