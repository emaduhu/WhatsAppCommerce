<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
class HealthController extends Controller {
    public function __invoke(){
        try{ DB::select('select 1'); $db='ok'; }
        catch(\Throwable $e){ $db='error'; }
        return response()->json(['status'=>$db==='ok' ? 'ok' : 'degraded','database'=>$db,'version'=>config('app.version','1.0.0')],$db==='ok' ? 200 : 503);
    }
}
