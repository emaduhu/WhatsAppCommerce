<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Category,Merchant};
use Illuminate\Http\Request;
class CategoryController extends Controller {
    public function index(Merchant $merchant){ return $merchant->categories()->orderBy('name')->paginate(50); }
    public function store(Request $request,Merchant $merchant){
        $data=$request->validate(['name'=>'required|string|max:160','description'=>'nullable|string|max:1000','status'=>'nullable|in:ACTIVE,INACTIVE,ARCHIVED']);
        return response()->json($merchant->categories()->create($data+['status'=>$data['status'] ?? 'ACTIVE']),201);
    }
    public function update(Request $request,Merchant $merchant,Category $category){
        abort_unless($category->merchant_id===$merchant->id,404);
        $category->update($request->validate(['name'=>'sometimes|string|max:160','description'=>'nullable|string|max:1000','status'=>'sometimes|in:ACTIVE,INACTIVE,ARCHIVED']));
        return $category;
    }
    public function destroy(Merchant $merchant,Category $category){
        abort_unless($category->merchant_id===$merchant->id,404);
        $category->update(['status'=>'ARCHIVED']);
        return response()->noContent();
    }
}
