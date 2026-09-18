<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureMerchantAccess {
    public function handle(Request $request,Closure $next): Response {
        $user=$request->user();
        if(!$user) abort(401);
        $merchant=$request->route('merchant');
        $merchantId=is_object($merchant) ? (int)$merchant->id : (int)$merchant;
        $allowed=DB::table('merchant_users')
            ->where('merchant_id',$merchantId)
            ->where('user_id',$user->id)
            ->where('is_active',true)
            ->exists();
        abort_unless($allowed,403,'You do not have access to this merchant.');
        return $next($request);
    }
}
