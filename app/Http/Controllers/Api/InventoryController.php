<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Merchant,Product,ProductVariant};
use App\Services\Commerce\InventoryService;
use Illuminate\Http\Request;
class InventoryController extends Controller {
    public function adjustProduct(Request $request,Merchant $merchant,Product $product,InventoryService $inventory){
        abort_unless($product->merchant_id===$merchant->id,404);
        $data=$request->validate(['quantity'=>'required|integer|not_in:0','type'=>'nullable|in:RESTOCK,ADJUSTMENT,RETURN,RELEASE,RESERVATION','notes'=>'nullable|string|max:1000']);
        $movement=$inventory->adjust($product,null,(int)$data['quantity'],$data['type'] ?? 'ADJUSTMENT',$request->user(),$data['notes'] ?? null);
        return response()->json(['product'=>$product->fresh(),'movement'=>$movement],201);
    }
    public function adjustVariant(Request $request,Merchant $merchant,Product $product,ProductVariant $variant,InventoryService $inventory){
        abort_unless($product->merchant_id===$merchant->id && $variant->merchant_id===$merchant->id && $variant->product_id===$product->id,404);
        $data=$request->validate(['quantity'=>'required|integer|not_in:0','type'=>'nullable|in:RESTOCK,ADJUSTMENT,RETURN,RELEASE,RESERVATION','notes'=>'nullable|string|max:1000']);
        $movement=$inventory->adjust($product,$variant,(int)$data['quantity'],$data['type'] ?? 'ADJUSTMENT',$request->user(),$data['notes'] ?? null);
        return response()->json(['variant'=>$variant->fresh(),'movement'=>$movement],201);
    }
}
