<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Customer,Merchant,Order,Product,ProductVariant};
use App\Services\Commerce\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller {
    public function index(Request $request,Merchant $merchant){
        $query=$merchant->orders()->with(['customer','items','payments'])->latest();
        if($request->filled('status')) $query->where('status',$request->string('status'));
        if($request->filled('payment_status')) $query->where('payment_status',$request->string('payment_status'));
        return $query->paginate((int)$request->query('per_page',30));
    }

    public function store(Request $request,Merchant $merchant,InventoryService $inventory){
        $data=$request->validate([
            'customer_id'=>'required|integer',
            'items'=>'required|array|min:1',
            'items.*.product_id'=>'required|integer',
            'items.*.product_variant_id'=>'nullable|integer',
            'items.*.quantity'=>'required|integer|min:1',
            'delivery_fee'=>'nullable|numeric|min:0',
            'discount'=>'nullable|numeric|min:0',
            'delivery_method'=>'nullable|string|max:50',
            'delivery_address'=>'nullable|string|max:1000',
            'notes'=>'nullable|string|max:1000',
        ]);

        return DB::transaction(function() use($data,$merchant,$inventory){
            $customer=Customer::where('merchant_id',$merchant->id)->findOrFail($data['customer_id']);
            $number='ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
            $order=Order::create([
                'merchant_id'=>$merchant->id,
                'customer_id'=>$customer->id,
                'number'=>$number,
                'order_number'=>$number,
                'currency'=>$merchant->currency,
                'delivery_fee'=>$data['delivery_fee'] ?? 0,
                'discount'=>$data['discount'] ?? 0,
                'delivery_method'=>$data['delivery_method'] ?? null,
                'delivery_address'=>$data['delivery_address'] ?? null,
                'notes'=>$data['notes'] ?? null,
            ]);
            $subtotal=0;
            foreach($data['items'] as $line){
                $product=Product::where('merchant_id',$merchant->id)->lockForUpdate()->findOrFail($line['product_id']);
                $variant=isset($line['product_variant_id']) ? ProductVariant::where('merchant_id',$merchant->id)->where('product_id',$product->id)->lockForUpdate()->findOrFail($line['product_variant_id']) : null;
                if($product->archived_at || (!$product->is_active && $product->status!=='ACTIVE')) abort(422,"Product {$product->name} is inactive");
                $availability=$inventory->checkAvailability($product,$variant,(int)$line['quantity']);
                if(!$availability['available']) abort(422,"Insufficient stock for {$product->name}");
                $unit=(float)($variant?->price ?? $product->price);
                $total=$unit*(int)$line['quantity'];
                $subtotal+=$total;
                $name=$variant ? trim($product->name.' '.$variant->name) : $product->name;
                $order->items()->create([
                    'product_id'=>$product->id,
                    'product_variant_id'=>$variant?->id,
                    'product_name'=>$name,
                    'name'=>$name,
                    'sku'=>$variant?->sku ?: $product->sku,
                    'quantity'=>(int)$line['quantity'],
                    'unit_price'=>$unit,
                    'total'=>$total,
                    'subtotal'=>$total,
                ]);
                if($product->track_stock) $inventory->deduct($product,$variant,(int)$line['quantity'],$order,'manual-order');
            }
            $order->update(['subtotal'=>$subtotal,'total'=>max(0,$subtotal+(float)$order->delivery_fee-(float)$order->discount),'status'=>'PENDING_PAYMENT']);
            $customer->increment('total_orders');
            $customer->increment('lifetime_value',(float)$order->total,['last_order_at'=>now()]);
            return response()->json($order->load('items'),201);
        });
    }

    public function show(Merchant $merchant,Order $order){ abort_unless($order->merchant_id===$merchant->id,404); return $order->load(['customer','items','payments']); }

    public function updateStatus(Request $request,Merchant $merchant,Order $order){
        abort_unless($order->merchant_id===$merchant->id,404);
        $data=$request->validate(['status'=>'required|in:DRAFT,PENDING_PAYMENT,CONFIRMED,PROCESSING,READY,SHIPPED,DELIVERED,COMPLETED,CANCELLED']);
        $order->update($data);
        return $order;
    }
}
