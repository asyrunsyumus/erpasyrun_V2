<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Customer $reseller;

    private Customer $reguler;

    private Product $fathul;

    private Product $arbain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->reseller = Customer::create(['name' => 'Toko Al-Furqan', 'type' => 'reseller']);
        $this->reguler = Customer::create(['name' => 'Ahmad', 'type' => 'reguler']);
        $this->fathul = Product::create(['title_latin' => 'Fathul Qarib', 'weight_grams' => 420, 'price_egp' => 8500, 'price_regular' => 150000, 'price_reseller' => 135000]);
        $this->arbain = Product::create(['title_latin' => 'Arbain', 'weight_grams' => 180, 'price_regular' => 70000, 'price_reseller' => 60000]);
    }

    private function stock(Product $p, int $qty): void
    {
        app(StockLedger::class)->move($p->id, $qty, 'import_awal', null, 50000);
    }

    private function stockOf(Product $p): int
    {
        return (int) DB::table('product_stock')->where('product_id', $p->id)->value('qty');
    }

    private function order(array $over = []): Order
    {
        $this->actingAs($this->admin)->post('/orders', $over + [
            'customer_id' => $this->reguler->id,
            'channel' => 'whatsapp',
            'payment_status' => 'paid',
            'shipping_fee' => 20000,
            'discount' => 10000,
            'items' => [['product_id' => $this->fathul->id, 'qty' => 2]],
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    public function test_price_follows_customer_type_and_totals_include_discount_and_shipping()
    {
        $o = $this->order(['customer_id' => $this->reseller->id]);
        $this->assertSame(135000, $o->items[0]->unit_price);
        $this->assertSame(270000, $o->subtotal);
        $this->assertSame(270000 - 10000 + 20000, $o->total);
    }

    /** Masalah lama #2: pesan 10, stok 7 → 7 dari gudang, 3 dibeli. Masalah #1: stok gudang ikut berkurang. */
    public function test_partial_stock_is_split_and_deducted()
    {
        $this->stock($this->fathul, 7);
        $o = $this->order(['items' => [['product_id' => $this->fathul->id, 'qty' => 10]]]);

        $item = $o->items[0];
        $this->assertSame(7, $item->qty_from_stock);
        $this->assertSame(3, $item->qty_to_buy);
        $this->assertSame(0, $this->stockOf($this->fathul));
        $this->assertSame(3, (int) DB::table('purchase_needs')->where('order_item_id', $item->id)->value('qty_needed'));
        $this->assertSame(OrderStatus::BelanjaMesir, $o->status);
    }

    /** Masalah lama #1: pesanan campuran juga mengurangi stok. */
    public function test_mixed_order_deducts_stock_for_stock_part()
    {
        $this->stock($this->arbain, 5);
        $o = $this->order(['items' => [['product_id' => $this->fathul->id, 'qty' => 1], ['product_id' => $this->arbain->id, 'qty' => 2]]]);

        $this->assertSame(3, $this->stockOf($this->arbain));
        $this->assertSame(OrderStatus::BelanjaMesir, $o->status);
    }

    public function test_all_from_stock_skips_to_packing()
    {
        $this->stock($this->fathul, 5);
        $o = $this->order();
        $this->assertSame(OrderStatus::SiapPacking, $o->status);
        $this->assertSame(0, DB::table('purchase_needs')->count());
    }

    public function test_unpaid_order_waits_and_needs_open_only_after_paid()
    {
        $o = $this->order(['payment_status' => 'unpaid']);
        $this->assertSame(OrderStatus::MenungguBayar, $o->status);
        $this->assertSame(0, DB::table('purchase_needs')->count());

        $this->actingAs($this->admin)->post("/orders/{$o->id}/paid", ['paid_at' => now()->toDateString()])->assertSessionHasNoErrors();
        $o->refresh();
        $this->assertSame('paid', $o->payment_status);
        $this->assertSame(OrderStatus::BelanjaMesir, $o->status);
        $this->assertSame(1, DB::table('purchase_needs')->count());

        // Tidak bisa ditandai lunas dua kali
        $this->actingAs($this->admin)->post("/orders/{$o->id}/paid", ['paid_at' => now()->toDateString()])->assertSessionHasErrors('paid_at');
        $this->assertSame(1, DB::table('purchase_needs')->count());
    }

    public function test_trusted_order_goes_to_shopping_immediately()
    {
        $o = $this->order(['payment_status' => 'trusted']);
        $this->assertSame(OrderStatus::BelanjaMesir, $o->status);
        $this->assertNull($o->paid_at);
        $this->assertSame(1, DB::table('purchase_needs')->count());
    }

    public function test_shopee_is_paid_without_invoice()
    {
        $o = $this->order(['channel' => 'shopee', 'payment_status' => null]);
        $this->assertSame('paid', $o->payment_status);
        $this->assertNull($o->invoice);
        $this->actingAs($this->admin)->get("/orders/{$o->id}/invoice")->assertNotFound();
    }

    public function test_numbers_are_sequential_per_month()
    {
        $a = $this->order();
        $b = $this->order();
        $stem = 'ASY-'.now()->format('ym').'-';
        $this->assertSame($stem.'0001', $a->number);
        $this->assertSame($stem.'0002', $b->number);
        $this->assertSame('INV-'.now()->format('ym').'-0002', $b->invoice->number);
    }

    public function test_product_without_price_is_rejected()
    {
        $noPrice = Product::create(['title_latin' => 'Baru', 'weight_grams' => 100]);
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->reguler->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $noPrice->id, 'qty' => 1]],
        ])->assertSessionHasErrors('items');
        $this->assertSame(0, Order::count());
    }

    public function test_status_history_is_recorded()
    {
        $o = $this->order(['payment_status' => 'unpaid']);
        $this->actingAs($this->admin)->post("/orders/{$o->id}/paid", ['paid_at' => now()->toDateString()]);
        $rows = DB::table('order_status_histories')->where('order_id', $o->id)->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('menunggu_bayar', $rows[1]->from_status);
        $this->assertSame('belanja_mesir', $rows[1]->to_status);
        $this->assertSame($this->admin->id, $rows[1]->user_id);
    }

    public function test_financial_sales_figure_subtracts_discount()
    {
        $this->order(); // subtotal 300.000, diskon 10.000
        $owner = User::factory()->role(Role::Owner)->create();
        $this->actingAs($owner)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('kpis.sales.value', 290000));
    }

    public function test_label_upload_and_access_by_role()
    {
        $o = $this->order();
        $this->actingAs($this->admin)->post("/orders/{$o->id}/label", ['file' => UploadedFile::fake()->create('resi.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();

        $this->actingAs(User::factory()->role(Role::Gudang)->create())->get("/orders/{$o->id}/label")->assertOk();
        $this->actingAs(User::factory()->role(Role::Mesir)->create())->get("/orders/{$o->id}/label")->assertForbidden();
        $this->actingAs(User::factory()->role(Role::Gudang)->create())->get("/orders/{$o->id}")->assertForbidden();
    }

    public function test_product_lookup_never_exposes_purchase_price()
    {
        $json = $this->actingAs($this->admin)->getJson('/orders/lookup/products?q=Fathul&type=reseller')->assertOk()->json();
        $this->assertSame(135000, $json[0]['price']);
        $this->assertArrayNotHasKey('price_egp', $json[0]);
    }

    public function test_cost_box_only_for_owner()
    {
        $o = $this->order();
        $this->actingAs($this->admin)->get("/orders/{$o->id}")->assertInertia(fn (Assert $p) => $p->where('cost', null));
    }
}
