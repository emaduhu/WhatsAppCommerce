<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\{Conversation,Merchant};
use Illuminate\Http\Request;
class ConversationController extends Controller {
    public function index(Request $request,Merchant $merchant){
        $query=Conversation::where('merchant_id',$merchant->id)->with(['customer','assignedUser'])->orderByDesc('last_message_at');
        if($request->filled('status')) $query->where('status',$request->string('status'));
        if($request->filled('mode')) $query->where('mode',$request->string('mode'));
        if($request->boolean('assigned_to_me')) $query->where('assigned_user_id',$request->user()->id);
        return $query->paginate((int)$request->query('per_page',30));
    }
    public function show(Merchant $merchant,Conversation $conversation){
        abort_unless($conversation->merchant_id===$merchant->id,404);
        return $conversation->load(['customer.orders'=>fn($q)=>$q->latest()->limit(10),'messages'=>fn($q)=>$q->latest()->limit(100),'activeCart.items.product','activeCart.items.variant','assignedUser']);
    }
    public function takeover(Request $request,Merchant $merchant,Conversation $conversation){
        abort_unless($conversation->merchant_id===$merchant->id,404);
        $conversation->update(['mode'=>'HUMAN','assigned_user_id'=>$request->user()->id,'current_state'=>'HUMAN_REQUIRED']);
        return $conversation;
    }
    public function release(Merchant $merchant,Conversation $conversation){
        abort_unless($conversation->merchant_id===$merchant->id,404);
        $conversation->update(['mode'=>'AI','assigned_user_id'=>null,'current_state'=>'NEW']);
        return $conversation;
    }
    public function resolve(Merchant $merchant,Conversation $conversation){
        abort_unless($conversation->merchant_id===$merchant->id,404);
        $conversation->update(['status'=>'RESOLVED','resolved_at'=>now()]);
        return $conversation;
    }
    public function close(Merchant $merchant,Conversation $conversation){
        abort_unless($conversation->merchant_id===$merchant->id,404);
        $conversation->update(['status'=>'CLOSED','resolved_at'=>now()]);
        return $conversation;
    }
}
