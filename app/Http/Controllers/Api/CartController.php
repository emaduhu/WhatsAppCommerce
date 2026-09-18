<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Cart,Merchant};
use Illuminate\Http\Request;
class CartController extends Controller {
    public function index(Request $request,Merchant $merchant){
        $query=$merchant->carts()->with(['customer','items.product','items.variant','order'])->latest();
        if($request->filled('status')) $query->where('status',$request->string('status'));
        return $query->paginate((int)$request->query('per_page',30));
    }
    public function show(Merchant $merchant,Cart $cart){
        abort_unless($cart->merchant_id===$merchant->id,404);
        return $cart->load(['customer','conversation','items.product','items.variant','order.payments']);
    }
}
