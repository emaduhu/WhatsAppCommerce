<?php
namespace Tests\Feature;

use App\Models\{InventoryMovement,Merchant,Product,ProductVariant,User};
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasApiHardeningTest extends TestCase {
    use RefreshDatabase;

    public function test_auth_me_product_variants_inventory_and_tenant_isolation(): void {
        $register=$this->postJson('/api/auth/register',[
            'name'=>'Owner One',
            'email'=>'owner1@example.com',
            'password'=>'StrongPass123',
            'password_confirmation'=>'StrongPass123',
            'business_name'=>'Demo Electronics',
        ])->assertCreated()->json();

        $token=$register['token'];
        $merchantId=$register['merchant']['id'];

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('merchants.0.id',$merchantId);

        $category=$this->withToken($token)->postJson("/api/merchants/{$merchantId}/categories",[
            'name'=>'Phones',
        ])->assertCreated()->json();

        $product=$this->withToken($token)->postJson("/api/merchants/{$merchantId}/products",[
            'category_id'=>$category['id'],
            'sku'=>'SAM-A55',
            'name'=>'Samsung Galaxy A55',
            'description'=>'Samsung A55 phone',
            'type'=>'PRODUCT',
            'price'=>850000,
            'stock_quantity'=>0,
            'track_stock'=>true,
            'variants'=>[[
                'sku'=>'SAM-A55-BLU-128',
                'name'=>'Blue 128GB',
                'attributes'=>['color'=>'Blue','storage'=>'128GB'],
                'price'=>850000,
                'stock_quantity'=>5,
            ]],
        ])->assertCreated()->json();

        $this->assertSame('Samsung Galaxy A55',$product['name']);
        $this->assertSame(1,ProductVariant::count());

        $variantId=ProductVariant::firstOrFail()->id;
        $this->withToken($token)->postJson("/api/merchants/{$merchantId}/products/{$product['id']}/variants/{$variantId}/inventory/adjust",[
            'quantity'=>3,
            'type'=>'RESTOCK',
            'notes'=>'supplier delivery',
        ])->assertCreated()->assertJsonPath('movement.type','RESTOCK');
        $this->assertSame(1,InventoryMovement::count());
        $this->assertSame(8,ProductVariant::firstOrFail()->stock_quantity);

        $other=User::create(['name'=>'Other','email'=>'other@example.com','password'=>'StrongPass123']);
        $otherMerchant=Merchant::create(['name'=>'Other Shop','slug'=>'other-shop']);
        $otherMerchant->users()->attach($other->id,['role'=>'OWNER','is_active'=>true]);
        Sanctum::actingAs($other);

        $this->getJson("/api/merchants/{$merchantId}/products")
            ->assertForbidden();
    }
}
