<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Customer,Merchant};
use Illuminate\Http\Request;
class CustomerController extends Controller {
    public function index(Request $request,Merchant $merchant){
        $query=$merchant->customers()->latest();
        $search=trim((string)$request->query('search',''));
        if($search!==''){
            $query->where(fn($q)=>$q->where('name','like',"%{$search}%")->orWhere('phone','like',"%{$search}%")->orWhere('whatsapp_number','like',"%{$search}%"));
        }
        return $query->paginate((int)$request->query('per_page',30));
    }
    public function show(Merchant $merchant,Customer $customer){ abort_unless($customer->merchant_id===$merchant->id,404); return $customer->load(['conversations','orders']); }
    public function update(Request $request,Merchant $merchant,Customer $customer){
        abort_unless($customer->merchant_id===$merchant->id,404);
        $customer->update($request->validate(['name'=>'sometimes|nullable|string|max:160','email'=>'sometimes|nullable|email','language'=>'sometimes|nullable|string|max:10','locale'=>'sometimes|nullable|string|max:10','marketing_opt_in'=>'sometimes|boolean','metadata'=>'sometimes|nullable|array']));
        return $customer;
    }
}
