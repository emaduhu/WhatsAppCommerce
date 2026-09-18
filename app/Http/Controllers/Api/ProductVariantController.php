<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Merchant,Product,ProductVariant};
use Illuminate\Http\Request;
class ProductVariantController extends Controller {
    public function store(Request $request,Merchant $merchant,Product $product){
        abort_unless($product->merchant_id===$merchant->id,404);
        $data=$request->validate(['sku'=>'nullable|string|max:80','name'=>'required|string|max:160','attributes'=>'nullable|array','price'=>'nullable|numeric|min:0','stock_quantity'=>'integer|min:0','status'=>'nullable|in:ACTIVE,INACTIVE,ARCHIVED']);
        return response()->json($product->variants()->create($data+['merchant_id'=>$merchant->id,'status'=>$data['status'] ?? 'ACTIVE']),201);
    }
    public function update(Request $request,Merchant $merchant,Product $product,ProductVariant $variant){
        abort_unless($product->merchant_id===$merchant->id && $variant->merchant_id===$merchant->id && $variant->product_id===$product->id,404);
        $variant->update($request->validate(['sku'=>'sometimes|nullable|string|max:80','name'=>'sometimes|string|max:160','attributes'=>'sometimes|nullable|array','price'=>'sometimes|nullable|numeric|min:0','stock_quantity'=>'sometimes|integer|min:0','status'=>'sometimes|in:ACTIVE,INACTIVE,ARCHIVED']));
        return $variant;
    }
    public function destroy(Merchant $merchant,Product $product,ProductVariant $variant){
        abort_unless($product->merchant_id===$merchant->id && $variant->merchant_id===$merchant->id && $variant->product_id===$product->id,404);
        $variant->update(['status'=>'ARCHIVED']);
        return response()->noContent();
    }
}
