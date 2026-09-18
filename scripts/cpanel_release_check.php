<?php
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$required=[
    'APP_KEY'=>config('app.key'),
    'DB_DATABASE'=>config('database.connections.mysql.database'),
    'DB_USERNAME'=>config('database.connections.mysql.username'),
    'WHATSAPP_WEBHOOK_VERIFY_TOKEN'=>config('services.whatsapp.verify_token'),
    'WHATSAPP_APP_SECRET'=>config('services.whatsapp.app_secret'),
];
$missing=[];
foreach($required as $key=>$value){ if((string)$value==='') $missing[]=$key; }
$checks=[
    'app_env'=>app()->environment(),
    'app_debug'=>config('app.debug') ? 'true' : 'false',
    'queue'=>config('queue.default'),
    'cache'=>config('cache.default'),
    'session'=>config('session.driver'),
    'missing_env'=>$missing,
];
try{ DB::select('select 1'); $checks['database']='ok'; }catch(Throwable $e){ $checks['database']='error'; }
echo json_encode($checks,JSON_PRETTY_PRINT).PHP_EOL;
exit($missing ? 1 : 0);
