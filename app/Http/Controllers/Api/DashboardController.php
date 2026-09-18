<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Conversation,Customer,Merchant,Message,Order,Payment,Product};

class DashboardController extends Controller {
    public function __invoke(Merchant $merchant){
        $start=now()->startOfDay();
        return [
            'today'=>[
                'sales'=>(float)Order::where('merchant_id',$merchant->id)->where('payment_status','PAID')->where('paid_at','>=',$start)->sum('total'),
                'orders'=>Order::where('merchant_id',$merchant->id)->where('created_at','>=',$start)->count(),
                'paid_orders'=>Order::where('merchant_id',$merchant->id)->where('payment_status','PAID')->where('paid_at','>=',$start)->count(),
                'pending_payments'=>Payment::where('merchant_id',$merchant->id)->whereIn('status',['PENDING','INIT_FAILED'])->count(),
                'new_customers'=>Customer::where('merchant_id',$merchant->id)->where('created_at','>=',$start)->count(),
                'messages'=>Message::where('merchant_id',$merchant->id)->where('created_at','>=',$start)->count(),
                'open_conversations'=>Conversation::where('merchant_id',$merchant->id)->where('status','OPEN')->count(),
                'ai_conversations'=>Conversation::where('merchant_id',$merchant->id)->where('status','OPEN')->where('mode','AI')->count(),
                'human_conversations'=>Conversation::where('merchant_id',$merchant->id)->where('status','OPEN')->where('mode','HUMAN')->count(),
            ],
            'totals'=>[
                'customers'=>$merchant->customers()->count(),
                'products'=>$merchant->products()->active()->count(),
                'orders'=>$merchant->orders()->count(),
            ],
            'recent_orders'=>$merchant->orders()->with('customer')->latest()->limit(8)->get(),
            'recent_conversations'=>$merchant->conversations()->with('customer')->latest('last_message_at')->limit(8)->get(),
            'payment_exceptions'=>$merchant->payments()->whereIn('status',['FAILED','CANCELLED','INIT_FAILED'])->latest()->limit(8)->get(),
            'low_stock'=>Product::where('merchant_id',$merchant->id)->where('track_stock',true)->where('stock_quantity','<=',5)->active()->orderBy('stock_quantity')->limit(10)->get(),
        ];
    }
}
