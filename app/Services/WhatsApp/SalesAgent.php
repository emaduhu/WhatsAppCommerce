<?php
namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsAppText;
use App\Models\{Cart,Conversation,Customer,Order,Payment,Product,ProductVariant,WhatsappAccount};
use App\Services\Commerce\InventoryService;
use App\Services\Payments\GenericPaymentGateway;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SalesAgent {
    private const ACTIVE_CART_STATUSES=['ACTIVE','DRAFT','AWAITING_DELIVERY','AWAITING_PAYMENT','PAYMENT_PENDING','CHECKOUT'];

    public function __construct(private GenericPaymentGateway $payments,private InventoryService $inventory) {}

    public function handle(WhatsappAccount $account,Customer $customer,Conversation $conversation,string $body): void {
        $body=trim($body);
        if($body==='') return;

        if($this->wantsHuman($body)){
            $conversation->update(['mode'=>'HUMAN','current_state'=>'HUMAN_REQUIRED']);
            $this->reply($account,$customer,'Nimekuelewa. Mtoa huduma ataendelea na mazungumzo haya.');
            return;
        }

        if($this->isCancel($body)){
            $this->activeCart($conversation)?->update(['status'=>'ABANDONED']);
            $conversation->update(['current_state'=>'NEW']);
            $this->reply($account,$customer,'Sawa, nimefuta cart hii. Unaweza kuanza upya kwa kuniandikia bidhaa unayotaka.');
            return;
        }

        $cart=$this->activeCart($conversation);
        if($cart?->status==='AWAITING_DELIVERY'){
            $this->captureDelivery($account,$customer,$cart,$body);
            return;
        }
        if($cart?->status==='AWAITING_PAYMENT'){
            $this->capturePayment($account,$customer,$cart,$body);
            return;
        }
        if($cart?->status==='PAYMENT_PENDING'){
            $this->reply($account,$customer,"Order {$cart->order?->number} ipo kwenye hatua ya malipo. Ukishalipa nitathibitisha hapa WhatsApp.");
            return;
        }

        if($this->isCatalogRequest($body)){
            $this->catalogReply($account,$customer,$body);
            return;
        }

        $request=$this->parsePurchase($body);
        if(!$request['query']){
            $this->catalogReply($account,$customer,$body);
            return;
        }

        $conversation->update(['current_state'=>'PRODUCT_SEARCH','context'=>['query'=>$request['query'],'quantity'=>$request['quantity']]]);
        $matches=$this->searchProducts($account,$request['query']);
        if($matches->isEmpty()){
            $this->reply($account,$customer,"Sijaipata bidhaa hiyo kwenye catalog. Tafadhali andika jina lingine, mfano: Samsung A55 blue mbili, au andika 'agent' kuongea na mhudumu.");
            return;
        }

        if($matches->count()>1 && $matches[0]['score']===$matches[1]['score']){
            $lines=$matches->take(5)->map(fn($match)=>$this->productLine($match['product'],$account,$match['variant'] ?? null))->implode("\n");
            $this->reply($account,$customer,"Nimepata bidhaa zinazofanana:\n{$lines}\n\nAndika jina kamili zaidi au SKU ili nichague sahihi.");
            return;
        }

        $match=$matches[0];
        $product=$match['product'];
        $variant=$match['variant'] ?? $this->matchingVariant($product,$request['terms']);
        if(!$variant && $product->variants()->where('status','ACTIVE')->exists()){
            $variants=$product->variants()->where('status','ACTIVE')->limit(8)->get()->map(fn($v)=>'- '.$this->variantLabel($v).' '.$this->money($this->priceFor($product,$v),$product->currency ?: $account->merchant->currency))->implode("\n");
            $conversation->update(['current_state'=>'VARIANT_REQUIRED']);
            $this->reply($account,$customer,"{$product->name} ina options hizi:\n{$variants}\n\nTafadhali taja option unayotaka, mfano Blue 128GB.");
            return;
        }

        $this->addProductToCart($account,$customer,$conversation,$product,$variant,$request['quantity']);
    }

    private function addProductToCart(WhatsappAccount $account,Customer $customer,Conversation $conversation,Product $product,?ProductVariant $variant,int $quantity): void {
        $availability=$this->inventory->checkAvailability($product,$variant,$quantity);
        if(!$availability['available']){
            $label=$variant ? $product->name.' '.$this->variantLabel($variant) : $product->name;
            $this->reply($account,$customer,"Samahani, {$label} ipo {$availability['available_quantity']} tu kwenye stock. Ungependa kuchukua kiasi hicho au kuchagua bidhaa nyingine?");
            return;
        }

        $cart=DB::transaction(function() use($account,$customer,$conversation,$product,$variant,$quantity){
            $cart=$this->activeCart($conversation) ?: Cart::create([
                'merchant_id'=>$account->merchant_id,
                'customer_id'=>$customer->id,
                'conversation_id'=>$conversation->id,
                'whatsapp_account_id'=>$account->id,
                'status'=>'ACTIVE',
                'currency'=>$product->currency ?: $account->merchant->currency,
                'expires_at'=>now()->addHours(24),
            ]);

            $price=$this->priceFor($product,$variant);
            $name=$variant ? $product->name.' '.$this->variantLabel($variant) : $product->name;
            $sku=$variant?->sku ?: $product->sku;
            $query=$cart->items()->where('product_id',$product->id);
            $variant ? $query->where('product_variant_id',$variant->id) : $query->whereNull('product_variant_id');
            $item=$query->lockForUpdate()->first();
            $newQuantity=$quantity+($item?->quantity ?? 0);
            $total=$price*$newQuantity;
            if($item){
                $item->update(['quantity'=>$newQuantity,'unit_price'=>$price,'total'=>$total,'subtotal'=>$total]);
            }else{
                $cart->items()->create([
                    'product_id'=>$product->id,
                    'product_variant_id'=>$variant?->id,
                    'name'=>$name,
                    'sku'=>$sku,
                    'quantity'=>$quantity,
                    'unit_price'=>$price,
                    'total'=>$price*$quantity,
                    'subtotal'=>$price*$quantity,
                    'metadata'=>['product'=>$product->metadata,'variant'=>$variant?->attributes],
                ]);
            }

            $this->recalculate($cart);
            $cart->update(['status'=>'AWAITING_DELIVERY']);
            $conversation->update(['current_state'=>'DELIVERY_ADDRESS']);
            return $cart->fresh('items');
        });

        $this->reply($account,$customer,$this->cartSummary($cart)." \n\nNimeitengeneza cart. Tafadhali tuma eneo/anwani ya delivery.");
    }

    private function captureDelivery(WhatsappAccount $account,Customer $customer,Cart $cart,string $body): void {
        $cart->update(['delivery_address'=>$body,'delivery_method'=>'DELIVERY','status'=>'AWAITING_PAYMENT']);
        $cart->conversation?->update(['current_state'=>'PAYMENT_METHOD']);
        $this->reply($account,$customer,$this->cartSummary($cart->fresh('items'))."\n\nDelivery: {$body}\n\nChagua malipo: tuma namba ya mobile money, mfano 2557XXXXXXX, au andika CASH.");
    }

    private function capturePayment(WhatsappAccount $account,Customer $customer,Cart $cart,string $body): void {
        if(preg_match('/\b(cash|cod|lipa cash|taslimu)\b/i',$body)){
            $order=$this->createOrderFromCart($cart,'CASH',null);
            $cart->update(['status'=>'CONVERTED','order_id'=>$order->id,'payment_method'=>'CASH']);
            $cart->conversation?->update(['current_state'=>'CONFIRMED']);
            $this->reply($account,$customer,"Order {$order->number} imeundwa. Jumla ni ".$this->money($order->total,$order->currency).". Utalipa CASH wakati wa delivery. Asante.");
            return;
        }

        $msisdn=$this->extractPhone($body);
        if(!$msisdn){
            $this->reply($account,$customer,'Tafadhali tuma namba ya mobile money kwa mfumo wa 2557XXXXXXX, au andika CASH kama utalipa wakati wa delivery.');
            return;
        }

        $order=$this->createOrderFromCart($cart,'MOBILE_MONEY',$msisdn);
        $payment=Payment::create([
            'merchant_id'=>$cart->merchant_id,
            'order_id'=>$order->id,
            'provider'=>config('services.payments.provider'),
            'reference'=>(string)Str::uuid(),
            'status'=>'PENDING',
            'amount'=>$order->total,
            'currency'=>$order->currency,
            'phone_number'=>$msisdn,
            'msisdn'=>$msisdn,
        ]);
        $cart->update(['status'=>'PAYMENT_PENDING','order_id'=>$order->id,'payment_method'=>'MOBILE_MONEY','payment_msisdn'=>$msisdn]);
        $cart->conversation?->update(['current_state'=>'AWAITING_PAYMENT']);

        try{
            $this->payments->initiatePayment($payment);
            $this->reply($account,$customer,"Order {$order->number} imeundwa. Jumla ni ".$this->money($order->total,$order->currency).". Ombi la malipo limetumwa kwenye {$msisdn}. Ukishalipa nitathibitisha hapa WhatsApp.");
        }catch(Throwable $e){
            $payment->update(['status'=>'INIT_FAILED','raw_response'=>['error'=>$e->getMessage()],'provider_response'=>['error'=>$e->getMessage()]]);
            $this->reply($account,$customer,"Order {$order->number} imeundwa. Jumla ni ".$this->money($order->total,$order->currency).". Malipo bado hayajaanzishwa kwa sababu payment gateway haijasanidiwa kikamilifu. Mhudumu atakusaidia kukamilisha malipo.");
        }
    }

    private function createOrderFromCart(Cart $cart,string $paymentMethod,?string $msisdn): Order {
        return DB::transaction(function() use($cart,$paymentMethod,$msisdn){
            $cart=$cart->fresh(['items','merchant','customer']);
            if(!$cart || $cart->items->isEmpty()) throw new RuntimeException('Cart is empty.');
            if($cart->order_id) return Order::findOrFail($cart->order_id);

            foreach($cart->items as $item){
                $product=Product::where('merchant_id',$cart->merchant_id)->lockForUpdate()->findOrFail($item->product_id);
                $variant=$item->product_variant_id ? ProductVariant::where('merchant_id',$cart->merchant_id)->where('product_id',$product->id)->lockForUpdate()->findOrFail($item->product_variant_id) : null;
                if(!$this->isProductActive($product)) throw new RuntimeException("Product {$product->name} is inactive.");
                $availability=$this->inventory->checkAvailability($product,$variant,$item->quantity);
                if(!$availability['available']) throw new RuntimeException("Insufficient stock for {$product->name}.");
            }

            $number='ORD-'.now()->format('ymd').'-'.Str::upper(Str::random(8));
            $order=Order::create([
                'merchant_id'=>$cart->merchant_id,
                'customer_id'=>$cart->customer_id,
                'cart_id'=>$cart->id,
                'number'=>$number,
                'order_number'=>$number,
                'status'=>$paymentMethod==='CASH' ? 'CONFIRMED' : 'PENDING_PAYMENT',
                'payment_status'=>'UNPAID',
                'fulfilment_status'=>'PENDING',
                'currency'=>$cart->currency,
                'subtotal'=>$cart->subtotal,
                'delivery_fee'=>$cart->delivery_fee,
                'discount'=>$cart->discount,
                'total'=>$cart->total,
                'delivery_method'=>$cart->delivery_method ?: 'DELIVERY',
                'delivery_address'=>$cart->delivery_address,
                'notes'=>$msisdn ? "Mobile money checkout: {$msisdn}" : null,
            ]);

            foreach($cart->items as $item){
                $product=Product::where('merchant_id',$cart->merchant_id)->lockForUpdate()->findOrFail($item->product_id);
                $variant=$item->product_variant_id ? ProductVariant::where('merchant_id',$cart->merchant_id)->where('product_id',$product->id)->lockForUpdate()->findOrFail($item->product_variant_id) : null;
                $order->items()->create([
                    'product_id'=>$product->id,
                    'product_variant_id'=>$variant?->id,
                    'product_name'=>$item->name,
                    'name'=>$item->name,
                    'sku'=>$item->sku,
                    'quantity'=>$item->quantity,
                    'unit_price'=>$item->unit_price,
                    'total'=>$item->total,
                    'subtotal'=>$item->subtotal ?: $item->total,
                ]);
                if($product->track_stock) $this->inventory->deduct($product,$variant,$item->quantity,$order,'checkout');
            }

            $cart->customer?->increment('total_orders');
            $cart->customer?->increment('lifetime_value',(float)$order->total,['last_order_at'=>now()]);
            return $order;
        });
    }

    private function catalogReply(WhatsappAccount $account,Customer $customer,string $body): void {
        if(!preg_match('/(catalog|bidhaa|menu|bei|price|product|stock)/i',$body)) return;
        $products=Product::where('merchant_id',$account->merchant_id)->active()->with('variants')->latest()->limit(8)->get();
        if($products->isEmpty()){
            $this->reply($account,$customer,'Catalog bado haina bidhaa zilizo active. Andika agent kuongea na mhudumu.');
            return;
        }
        $lines=$products->map(fn($product)=>$this->productLine($product,$account))->implode("\n");
        $this->reply($account,$customer,"Hizi ni baadhi ya bidhaa:\n{$lines}\n\nKuagiza, andika mfano: Nataka Samsung A55 blue mbili.");
    }

    private function parsePurchase(string $body): array {
        $tokens=$this->tokens($body);
        $quantity=1;
        $quantityWords=['moja'=>1,'one'=>1,'mbili'=>2,'two'=>2,'tatu'=>3,'three'=>3,'nne'=>4,'four'=>4,'tano'=>5,'five'=>5,'sita'=>6,'six'=>6,'saba'=>7,'seven'=>7,'nane'=>8,'eight'=>8,'tisa'=>9,'nine'=>9,'kumi'=>10,'ten'=>10];
        foreach($tokens as $token){
            if(isset($quantityWords[$token])) $quantity=$quantityWords[$token];
            elseif(ctype_digit($token) && (int)$token>0 && (int)$token<=100) $quantity=(int)$token;
        }

        $stop=['nataka','nahitaji','naomba','nunua','ninue','weka','niwekee','nipatie','bidhaa','order','oda','buy','want','need','please','tafadhali','qty','pcs','pc'];
        $queryTokens=array_values(array_filter($tokens, fn($token)=>!in_array($token,$stop,true) && !isset($quantityWords[$token]) && !ctype_digit($token)));
        return ['quantity'=>$quantity,'query'=>implode(' ',$queryTokens),'terms'=>$queryTokens];
    }

    private function searchProducts(WhatsappAccount $account,string $query): Collection {
        $terms=array_values(array_filter($this->tokens($query), fn($term)=>mb_strlen($term)>=2));
        if(!$terms) return collect();

        $products=Product::where('merchant_id',$account->merchant_id)->active()->with('variants')
            ->where(function($q) use($terms){
                foreach($terms as $term){
                    $like='%'.$term.'%';
                    $q->orWhere('name','like',$like)->orWhere('sku','like',$like)->orWhere('description','like',$like)->orWhere('metadata','like',$like);
                }
            })
            ->limit(50)
            ->get();

        if($products->count()<10){
            $variantProducts=Product::where('products.merchant_id',$account->merchant_id)->active()->with('variants')
                ->whereHas('variants',function($q) use($terms){
                    foreach($terms as $term){
                        $like='%'.$term.'%';
                        $q->orWhere('name','like',$like)->orWhere('sku','like',$like)->orWhere('attributes','like',$like);
                    }
                })->limit(50)->get();
            $products=$products->merge($variantProducts)->unique('id')->values();
        }

        return $products->map(function($product) use($terms,$query){
                $variant=$this->matchingVariant($product,$terms);
                return ['product'=>$product,'variant'=>$variant,'score'=>$this->scoreProduct($product,$terms,$query,$variant)];
            })
            ->filter(fn($match)=>$match['score']>0)
            ->sortByDesc('score')
            ->values();
    }

    private function scoreProduct(Product $product,array $terms,string $query,?ProductVariant $variant=null): int {
        $text=$this->normalize(trim($product->name.' '.$product->sku.' '.$product->description.' '.json_encode($product->metadata ?? []).' '.$this->variantSearchText($product)));
        $score=str_contains($text,$this->normalize($query)) ? 20 : 0;
        foreach($terms as $term){
            if(str_contains($text,$term)) $score+=10;
            if($product->sku && $this->normalize($product->sku)===$term) $score+=15;
        }
        if($variant) $score+=8;
        return $score;
    }

    private function matchingVariant(Product $product,array $terms): ?ProductVariant {
        $variants=$product->relationLoaded('variants') ? $product->variants : $product->variants()->where('status','ACTIVE')->get();
        $matches=$variants->filter(function($variant) use($terms){
            $text=$this->normalize($variant->name.' '.$variant->sku.' '.json_encode($variant->attributes ?? []));
            foreach($terms as $term){ if(str_contains($text,$term)) return true; }
            return false;
        })->values();
        return $matches->count()===1 ? $matches->first() : null;
    }

    private function variantSearchText(Product $product): string {
        $variants=$product->relationLoaded('variants') ? $product->variants : collect();
        return $variants->map(fn($variant)=>$variant->name.' '.$variant->sku.' '.json_encode($variant->attributes ?? []))->implode(' ');
    }

    private function priceFor(Product $product,?ProductVariant $variant): float {
        return (float)($variant?->price ?? $product->price);
    }

    private function variantLabel(ProductVariant $variant): string {
        $attributes=collect($variant->attributes ?? [])->map(fn($value,$key)=>$value)->implode(' ');
        return trim($variant->name.' '.$attributes) ?: (string)$variant->sku;
    }

    private function isProductActive(Product $product): bool {
        return $product->archived_at===null && ($product->is_active || $product->status==='ACTIVE');
    }

    private function recalculate(Cart $cart): void {
        $subtotal=(float)$cart->items()->sum('total');
        $cart->update(['subtotal'=>$subtotal,'total'=>max(0,$subtotal+(float)$cart->delivery_fee-(float)$cart->discount)]);
    }

    private function activeCart(Conversation $conversation): ?Cart {
        return Cart::where('conversation_id',$conversation->id)->whereIn('status',self::ACTIVE_CART_STATUSES)->latest()->with(['items','order','conversation'])->first();
    }

    private function cartSummary(Cart $cart): string {
        $cart=$cart->fresh('items');
        $lines=$cart->items->map(fn($item)=>"{$item->quantity} x {$item->name} = ".$this->money($item->total,$cart->currency))->implode("\n");
        return "Cart yako:\n{$lines}\nJumla: ".$this->money($cart->total,$cart->currency);
    }

    private function productLine(Product $product,WhatsappAccount $account,?ProductVariant $variant=null): string {
        if($variant){
            $stock=$product->track_stock ? " stock {$variant->stock_quantity}" : '';
            return "- {$product->name} {$this->variantLabel($variant)} ({$this->money($this->priceFor($product,$variant),$product->currency ?: $account->merchant->currency)}{$stock})";
        }
        $stock=$product->track_stock ? " stock {$product->stock_quantity}" : '';
        return "- {$product->name} ({$this->money($product->price,$product->currency ?: $account->merchant->currency)}{$stock})";
    }

    private function money(mixed $amount,string $currency): string {
        return number_format((float)$amount,0,'.',',').' '.$currency;
    }

    private function extractPhone(string $body): ?string {
        $digits=preg_replace('/\D+/','',$body);
        if(!$digits) return null;
        if(str_starts_with($digits,'0')) $digits='255'.substr($digits,1);
        if(strlen($digits)>=10 && strlen($digits)<=15) return $digits;
        return null;
    }

    private function wantsHuman(string $body): bool {
        return (bool)preg_match('/\b(human|agent|customer care|support|mtu|msaada|mhudumu|nataka kuongea na mtu)\b/i',$body);
    }

    private function isCancel(string $body): bool {
        return (bool)preg_match('/\b(cancel|futa|acha|sitaki|anza upya)\b/i',$body);
    }

    private function isCatalogRequest(string $body): bool {
        return (bool)preg_match('/\b(catalog|bidhaa|menu|orodha|products?|stock|bei)\b/i',$body);
    }

    private function reply(WhatsappAccount $account,Customer $customer,string $body): void {
        SendWhatsAppText::dispatch($account->id,$customer->id,$body);
    }

    private function tokens(string $text): array {
        return array_values(array_filter(preg_split('/[^\pL\pN]+/u',$this->normalize($text)) ?: []));
    }

    private function normalize(string $text): string {
        return trim(preg_replace('/\s+/',' ',mb_strtolower($text)));
    }
}
