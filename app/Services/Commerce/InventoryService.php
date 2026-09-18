<?php
namespace App\Services\Commerce;

use App\Models\{InventoryMovement,Order,Product,ProductVariant,User};
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService {
    public function checkAvailability(Product $product,?ProductVariant $variant,int $quantity): array {
        $target=$variant ?: $product;
        $available=!(bool)$product->track_stock || (int)$target->stock_quantity >= $quantity;
        return ['available'=>$available,'available_quantity'=>(int)$target->stock_quantity,'requested_quantity'=>$quantity];
    }

    public function deduct(Product $product,?ProductVariant $variant,int $quantity,?Order $order=null,?string $reference=null): void {
        $this->move($product,$variant,-abs($quantity),'SALE',$order,null,$reference);
    }

    public function reserve(Product $product,?ProductVariant $variant,int $quantity,?string $reference=null): void {
        $this->move($product,$variant,-abs($quantity),'RESERVATION',null,null,$reference);
    }

    public function release(Product $product,?ProductVariant $variant,int $quantity,?string $reference=null): void {
        $this->move($product,$variant,abs($quantity),'RELEASE',null,null,$reference);
    }

    public function adjust(Product $product,?ProductVariant $variant,int $quantity,string $type='ADJUSTMENT',?User $user=null,?string $notes=null): InventoryMovement {
        return $this->move($product,$variant,$quantity,$type,null,$user,$notes);
    }

    private function move(Product $product,?ProductVariant $variant,int $quantity,string $type,?Order $order=null,?User $user=null,?string $referenceOrNotes=null): InventoryMovement {
        return DB::transaction(function() use($product,$variant,$quantity,$type,$order,$user,$referenceOrNotes){
            $product=Product::where('merchant_id',$product->merchant_id)->lockForUpdate()->findOrFail($product->id);
            $target=$product;
            if($variant){
                $target=ProductVariant::where('merchant_id',$product->merchant_id)->where('product_id',$product->id)->lockForUpdate()->findOrFail($variant->id);
            }

            $before=(int)$target->stock_quantity;
            $after=$before+$quantity;
            if($product->track_stock && $after<0){
                throw new RuntimeException("Insufficient stock for {$product->name}.");
            }

            $target->update(['stock_quantity'=>$after]);

            return InventoryMovement::create([
                'merchant_id'=>$product->merchant_id,
                'product_id'=>$product->id,
                'product_variant_id'=>$variant?->id,
                'order_id'=>$order?->id,
                'user_id'=>$user?->id,
                'type'=>$type,
                'quantity'=>$quantity,
                'stock_before'=>$before,
                'stock_after'=>$after,
                'reference'=>$order?->number ?? $order?->order_number,
                'notes'=>$referenceOrNotes,
            ]);
        });
    }
}
