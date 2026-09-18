<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppText;
use App\Models\{Conversation,Merchant,Order,Payment,PaymentTransaction};
use App\Services\Payments\GenericPaymentGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller {
    public function index(Request $request,Merchant $merchant){
        $query=$merchant->payments()->with(['order.customer'])->latest();
        if($request->filled('status')) $query->where('status',$request->string('status'));
        return $query->paginate((int)$request->query('per_page',30));
    }

    public function show(Merchant $merchant,Payment $payment){
        abort_unless($payment->merchant_id===$merchant->id,404);
        return $payment->load(['order.customer','transactions']);
    }

    public function initiate(Request $request,Merchant $merchant,Order $order,GenericPaymentGateway $gateway){
        abort_unless($order->merchant_id===$merchant->id,404);
        $data=$request->validate(['msisdn'=>'required|string|max:20']);
        $payment=Payment::create([
            'merchant_id'=>$merchant->id,
            'order_id'=>$order->id,
            'provider'=>config('services.payments.provider'),
            'reference'=>(string)Str::uuid(),
            'status'=>'PENDING',
            'amount'=>$order->total,
            'currency'=>$order->currency,
            'phone_number'=>$data['msisdn'],
            'msisdn'=>$data['msisdn'],
        ]);
        return response()->json(['payment'=>$payment,'provider'=>$gateway->initiatePayment($payment)],201);
    }

    public function webhook(Request $request,GenericPaymentGateway $gateway,?string $provider=null){
        $raw=$request->getContent();
        if(!$gateway->verifyCallback($raw,$request->header('X-Signature'))){
            return response()->json(['message'=>'Invalid signature'],401);
        }

        $payload=$request->json()->all();
        $provider=$provider ?: (string)config('services.payments.provider','generic');
        $reference=(string)($payload['id'] ?? $payload['reference'] ?? $payload['provider_transaction_id'] ?? '');
        $eventKey=$reference !== '' ? $reference : hash('sha256',$raw);
        $status=strtoupper((string)($payload['status'] ?? ''));

        $transaction=PaymentTransaction::firstOrCreate(
            ['provider'=>$provider,'event_key'=>$eventKey],
            [
                'provider_transaction_id'=>$reference ?: null,
                'status'=>'RECEIVED',
                'amount'=>isset($payload['amount']) ? (float)$payload['amount'] : null,
                'currency'=>$payload['currency'] ?? null,
                'payload'=>$payload,
            ]
        );
        if($transaction->processed_at){
            return response()->json(['received'=>true,'duplicate'=>true]);
        }

        $confirmation=null;
        DB::transaction(function() use($payload,$reference,$status,$transaction,&$confirmation){
            $payment=Payment::query()
                ->where('provider_reference',$reference)
                ->orWhere('provider_transaction_id',$reference)
                ->orWhere('reference',$reference)
                ->when(ctype_digit($reference),fn($q)=>$q->orWhere('id',(int)$reference))
                ->lockForUpdate()
                ->first();

            if(!$payment){
                $transaction->update(['status'=>'UNMATCHED','processed_at'=>now()]);
                return;
            }

            $transaction->update(['merchant_id'=>$payment->merchant_id,'payment_id'=>$payment->id]);
            if($this->amountMismatch($payment,$payload) || $this->currencyMismatch($payment,$payload)){
                $transaction->update(['status'=>'FAILED','processed_at'=>now(),'payload'=>$payload+['verification_error'=>'amount_or_currency_mismatch']]);
                return;
            }

            if(in_array($payment->status,['PAID','SUCCESS'],true)){
                $transaction->update(['status'=>'DUPLICATE','processed_at'=>now()]);
                return;
            }

            if(in_array($status,['PAID','SUCCESS','COMPLETED'],true)){
                $payment->update([
                    'status'=>'PAID',
                    'provider_transaction_id'=>$payment->provider_transaction_id ?: $reference,
                    'paid_at'=>now(),
                    'raw_response'=>$payload,
                    'provider_response'=>$payload,
                ]);
                $order=$payment->order()->with('customer')->lockForUpdate()->firstOrFail();
                $order->update(['payment_status'=>'PAID','status'=>'CONFIRMED','paid_at'=>now()]);
                $transaction->update(['status'=>'PROCESSED','processed_at'=>now()]);

                $conversation=Conversation::where('merchant_id',$order->merchant_id)->where('customer_id',$order->customer_id)->latest('last_message_at')->first();
                if($conversation){
                    $confirmation=[
                        'account_id'=>$conversation->whatsapp_account_id,
                        'customer_id'=>$order->customer_id,
                        'body'=>"Payment received successfully.\nOrder: {$order->number}\nAmount: ".number_format((float)$order->total,0,'.',',')." {$order->currency}\nStatus: Confirmed\nThank you for your order.",
                    ];
                }
            }elseif(in_array($status,['FAILED','CANCELLED'],true)){
                $payment->update(['status'=>$status,'raw_response'=>$payload,'provider_response'=>$payload]);
                $transaction->update(['status'=>'PROCESSED','processed_at'=>now()]);
            }else{
                $payment->update(['raw_response'=>$payload,'provider_response'=>$payload]);
                $transaction->update(['status'=>'PENDING','processed_at'=>now()]);
            }
        });

        if($confirmation) SendWhatsAppText::dispatch($confirmation['account_id'],$confirmation['customer_id'],$confirmation['body']);
        return response()->json(['received'=>true]);
    }

    private function amountMismatch(Payment $payment,array $payload): bool {
        if(!isset($payload['amount'])) return false;
        return abs((float)$payload['amount']-(float)$payment->amount) > 0.01;
    }

    private function currencyMismatch(Payment $payment,array $payload): bool {
        if(!isset($payload['currency'])) return false;
        return strtoupper((string)$payload['currency']) !== strtoupper((string)$payment->currency);
    }
}
