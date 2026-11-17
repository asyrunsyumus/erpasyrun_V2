<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductWeightTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $cust;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->cust = Customer::create(['name' => 'Ahmad', 'type' => 'reguler']);
    }

    private function order(Product $p, ?int $weight = null)
    {
        return $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $p->id, 'qty' => 1, 'weight_grams' => $weight]],
        ]);
    }

    public function test_product_can_be_saved_without_weight()
    {
        $this->actingAs($this->admin)->post('/products', ['title_latin' => 'Kitab Baru'])->assertSessionHasNoErrors();
        $this->assertNull(Product::first()->weight_grams);
    }

    public function test_order_requires_weight_for_product_without_weight()
    {
        $p = Product::create(['title_latin' => 'Kitab Baru', 'price_regular' => 50000, 'price_reseller' => 45000]);
        $this->order($p)->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    public function test_weight_filled_in_order_is_saved_to_master_product()
    {
        $p = Product::create(['title_latin' => 'Kitab Baru', 'price_regular' => 50000, 'price_reseller' => 45000]);
        $this->order($p, 350)->assertSessionHasNoErrors();

        $this->assertSame(350, $p->refresh()->weight_grams);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.weight_from_order']);

        // Order berikutnya: berat sudah otomatis, tidak perlu diisi lagi
        $this->order($p)->assertSessionHasNoErrors();
        $this->assertSame(2, Order::count());
    }

    public function test_existing_weight_is_never_overwritten_from_order_form()
    {
        $p = Product::create(['title_latin' => 'Fathul Qarib', 'weight_grams' => 420, 'price_regular' => 50000, 'price_reseller' => 45000]);
        $this->order($p, 999)->assertSessionHasNoErrors();
        $this->assertSame(420, $p->refresh()->weight_grams);
    }

    public function test_lookup_reports_missing_weight_as_null()
    {
        Product::create(['title_latin' => 'Kitab Baru', 'price_regular' => 50000, 'price_reseller' => 45000]);
        $json = $this->actingAs($this->admin)->getJson('/orders/lookup/products?q=Kitab')->json();
        $this->assertNull($json[0]['weight_grams']);
    }
}
