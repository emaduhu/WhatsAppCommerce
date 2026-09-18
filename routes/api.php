<?php
use App\Http\Controllers\Api\{AuthController,CartController,CategoryController,ConversationController,CustomerController,DashboardController,HealthController,InventoryController,OrderController,PaymentController,ProductController,ProductVariantController,WhatsAppController,WhatsAppWebhookController};
use Illuminate\Support\Facades\Route;

Route::get('health',HealthController::class);

Route::prefix('auth')->group(function(){
    Route::post('register',[AuthController::class,'register'])->middleware('throttle:10,1');
    Route::post('login',[AuthController::class,'login'])->middleware('throttle:10,1');
});

Route::get('webhooks/whatsapp',[WhatsAppWebhookController::class,'verify']);
Route::post('webhooks/whatsapp',[WhatsAppWebhookController::class,'receive'])->middleware('throttle:120,1');
Route::post('webhooks/payments/generic',[PaymentController::class,'webhook'])->middleware('throttle:60,1');
Route::post('webhooks/payments/{provider}',[PaymentController::class,'webhook'])->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function(){
    Route::get('auth/me',[AuthController::class,'me']);
    Route::post('auth/logout',[AuthController::class,'logout']);

    Route::prefix('merchants/{merchant}')->middleware('merchant')->group(function(){
        Route::get('dashboard',DashboardController::class);
        Route::apiResource('categories',CategoryController::class)->except(['show']);
        Route::apiResource('products',ProductController::class);
        Route::post('products/{product}/variants',[ProductVariantController::class,'store']);
        Route::patch('products/{product}/variants/{variant}',[ProductVariantController::class,'update']);
        Route::delete('products/{product}/variants/{variant}',[ProductVariantController::class,'destroy']);
        Route::post('products/{product}/inventory/adjust',[InventoryController::class,'adjustProduct']);
        Route::post('products/{product}/variants/{variant}/inventory/adjust',[InventoryController::class,'adjustVariant']);
        Route::get('customers',[CustomerController::class,'index']);
        Route::get('customers/{customer}',[CustomerController::class,'show']);
        Route::patch('customers/{customer}',[CustomerController::class,'update']);
        Route::get('conversations',[ConversationController::class,'index']);
        Route::get('conversations/{conversation}',[ConversationController::class,'show']);
        Route::post('conversations/{conversation}/takeover',[ConversationController::class,'takeover']);
        Route::post('conversations/{conversation}/release',[ConversationController::class,'release']);
        Route::post('conversations/{conversation}/resolve',[ConversationController::class,'resolve']);
        Route::post('conversations/{conversation}/close',[ConversationController::class,'close']);
        Route::get('carts',[CartController::class,'index']);
        Route::get('carts/{cart}',[CartController::class,'show']);
        Route::get('orders',[OrderController::class,'index']);
        Route::post('orders',[OrderController::class,'store']);
        Route::get('orders/{order}',[OrderController::class,'show']);
        Route::patch('orders/{order}/status',[OrderController::class,'updateStatus']);
        Route::get('payments',[PaymentController::class,'index']);
        Route::get('payments/{payment}',[PaymentController::class,'show']);
        Route::post('orders/{order}/payments',[PaymentController::class,'initiate'])->middleware('throttle:20,1');
        Route::get('whatsapp/onboarding/config',[WhatsAppController::class,'onboardingConfig']);
        Route::post('whatsapp/onboarding/complete',[WhatsAppController::class,'completeOnboarding']);
        Route::get('whatsapp/accounts',[WhatsAppController::class,'accounts']);
        Route::post('whatsapp/accounts/{account}/register',[WhatsAppController::class,'retryRegistration']);
        Route::post('whatsapp/accounts',[WhatsAppController::class,'connect']);
        Route::post('whatsapp/messages/text',[WhatsAppController::class,'sendText']);
        Route::post('whatsapp/messages/template',[WhatsAppController::class,'sendTemplate']);
    });
});
