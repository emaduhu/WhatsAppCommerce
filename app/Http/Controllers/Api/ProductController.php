<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Category,Merchant,Product};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProductController extends Controller {
    public function index(Request $request,Merchant $merchant){
        $query=$merchant->products()->with(['category','variants','images'])->latest();
        if($request->filled('status')) $query->where('status',$request->string('status'));
        if($request->filled('search')){
            $search=trim((string)$request->query('search'));
            $query->where(fn($q)=>$q->where('name','like',"%{$search}%")->orWhere('sku','like',"%{$search}%")->orWhere('description','like',"%{$search}%"));
        }
        return $query->paginate((int)$request->query('per_page',30));
    }
    public function store(Request $request,Merchant $merchant){
        $data=$this->validated($request);
        return DB::transaction(function() use($merchant,$data){
            if(isset($data['category_id'])) Category::where('merchant_id',$merchant->id)->findOrFail($data['category_id']);
            $variants=$data['variants'] ?? [];
            unset($data['variants']);
            $product=$merchant->products()->create($data+['currency'=>$data['currency'] ?? $merchant->currency,'status'=>$data['status'] ?? 'ACTIVE','is_active'=>$data['is_active'] ?? true]);
            foreach($variants as $variant){
                $product->variants()->create($variant+['merchant_id'=>$merchant->id,'status'=>$variant['status'] ?? 'ACTIVE']);
            }
            return response()->json($product->load(['category','variants','images']),201);
        });
    }
    public function show(Merchant $merchant,Product $product){ abort_unless($product->merchant_id===$merchant->id,404); return $product->load(['category','variants','images','inventoryMovements'=>fn($q)=>$q->latest()->limit(20)]); }
    public function update(Request $request,Merchant $merchant,Product $product){
        abort_unless($product->merchant_id===$merchant->id,404);
        $data=$this->validated($request,true);
        if(isset($data['category_id'])) Category::where('merchant_id',$merchant->id)->findOrFail($data['category_id']);
        $product->update($data);
        return $product->load(['category','variants','images']);
    }
    public function destroy(Merchant $merchant,Product $product){
        abort_unless($product->merchant_id===$merchant->id,404);
        $product->update(['status'=>'ARCHIVED','is_active'=>false,'archived_at'=>now()]);
        return response()->noContent();
    }
    private function validated(Request $request,bool $partial=false): array {
        $sometimes=$partial ? 'sometimes|' : '';
        return $request->validate([
            'category_id'=>$sometimes.'nullable|integer',
            'sku'=>$sometimes.'nullable|string|max:80',
            'name'=>$sometimes.'required|string|max:180',
            'description'=>'nullable|string',
            'type'=>$sometimes.'required|in:PRODUCT,SERVICE',
            'price'=>$sometimes.'required|numeric|min:0',
            'cost_price'=>'nullable|numeric|min:0',
            'currency'=>$sometimes.'nullable|string|size:3',
            'stock_quantity'=>$sometimes.'integer|min:0',
            'track_stock'=>$sometimes.'boolean',
            'status'=>$sometimes.'nullable|in:ACTIVE,INACTIVE,ARCHIVED',
            'is_active'=>$sometimes.'boolean',
            'metadata'=>'nullable|array',
            'variants'=>'sometimes|array',
            'variants.*.sku'=>'nullable|string|max:80',
            'variants.*.name'=>'required_with:variants|string|max:160',
            'variants.*.attributes'=>'nullable|array',
            'variants.*.price'=>'nullable|numeric|min:0',
            'variants.*.stock_quantity'=>'integer|min:0',
            'variants.*.status'=>'nullable|in:ACTIVE,INACTIVE,ARCHIVED',
        ]);
    }
}
