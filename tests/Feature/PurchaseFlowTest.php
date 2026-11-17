<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Maktabah;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $mesir;

    private User $owner;

    private Product $book;

    private Maktabah $toko;

    private Maktabah $toko2;

    private Customer $cust;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->mesir = User::factory()->role(Role::Mesir)->create();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $this->toko2 = Maktabah::create(['name' => 'Dar Ibn Hazm']);
        $this->book = Product::create(['title_latin' => 'Fathul Qarib', 'weight_grams' => 420, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 8500, 'price_regular' => 150000, 'price_reseller' => 135000]);
        $this->cust = Customer::create(['name' => 'Ahmad Rahasia', 'type' => 'reguler']);
    }

    private function order(int $qty): Order
    {
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $this->book->id, 'qty' => $qty]],
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    private function buy(int $qty, int $price = 8500, ?User $as = null, ?int $maktabah = null): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($as ?? $this->mesir)->post('/purchases', [
            'product_id' => $this->book->id, 'maktabah_id' => $maktabah ?? $this->toko->id, 'qty' => $qty,
            'price_egp' => $price, 'rate' => 3125000, 'purchased_at' => now()->toDateString(),
        ]);
    }

    private function stock(): int
    {
        return (int) DB::table('product_stock')->where('product_id', $this->book->id)->value('qty');
    }

    public function test_list_grouped_by_maktabah_and_hides_sales_data_from_mesir()
    {
        $this->order(3);
        $res = $this->actingAs($this->mesir)->get('/purchases')->assertOk();
        $res->assertInertia(fn (Assert $p) => $p
            ->where('groups.0.maktabah', 'Dar al-Salam')
            ->where('groups.0.items.0.qty', 3)
            ->missing('groups.0.items.0.price_regular')
            ->missing('groups.0.items.0.price_reseller'));
        $this->assertStringNotContainsString('Ahmad Rahasia', $res->getContent());
        $this->assertStringNotContainsString('150000', $res->getContent());
    }

    public function test_exact_purchase_completes_order_and_row_disappears()
    {
        $o = $this->order(3);
        $this->buy(3)->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::MenungguBagasi, $o->refresh()->status);
        $this->actingAs($this->mesir)->get('/purchases')->assertInertia(fn (Assert $p) => $p->has('groups', 0));
        $this->assertSame(1, DB::table('purchases')->value('cost_idr') > 0 ? 1 : 0);
        // 3 × 85 EGP × 312,5 = 79.687,5 → 79.688
        $this->assertSame(79688, (int) DB::table('purchases')->value('cost_idr'));
    }

    public function test_shortfall_keeps_row_and_notifies_each_owner()
    {
        $owner2 = User::factory()->role(Role::Owner)->create();
        $o = $this->order(5);
        $this->buy(2)->assertSessionHas('success', fn ($m) => str_contains($m, 'Masih kurang 3'));

        $this->assertSame(OrderStatus::BelanjaMesir, $o->refresh()->status);
        $this->assertSame(1, $this->owner->notifications()->count());
        $this->assertSame(1, $owner2->notifications()->count());
        $this->assertSame(0, $this->mesir->notifications()->count());

        // Belanja lagi di baris yang sama
        $this->buy(3);
        $this->assertSame(OrderStatus::MenungguBagasi, $o->refresh()->status);
    }

    public function test_surplus_goes_to_stock_with_cost()
    {
        $this->order(2);
        $this->buy(5)->assertSessionHas('success', fn ($m) => str_contains($m, 'Kelebihan 3'));
        // Kelebihan menunggu dicentang gudang; stok belum bertambah.
        $this->assertSame(0, $this->stock());
        $this->assertSame(3, (int) DB::table('purchase_arrivals')->where('source', 'lebih')->value('qty'));
        $this->actingAs(User::factory()->role(Role::Gudang)->create())->post('/warehouse/arrivals/receive-all');
        $this->assertSame(26563, (int) DB::table('product_stock')->value('avg_cost_idr')); // 85 × 312,5
    }

    public function test_fifo_oldest_order_first()
    {
        $first = $this->order(2);
        $this->travel(1)->minutes();
        $second = $this->order(2);

        $this->buy(2);
        $this->assertSame(OrderStatus::MenungguBagasi, $first->refresh()->status);
        $this->assertSame(OrderStatus::BelanjaMesir, $second->refresh()->status);
    }

    public function test_price_change_updates_product_and_is_audited()
    {
        $this->order(1);
        $this->buy(1, 9000)->assertSessionHas('success', fn ($m) => str_contains($m, 'diperbarui'));
        $this->assertSame(9000, $this->book->refresh()->price_egp);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.price_egp_from_purchase']);
    }

    public function test_bought_at_other_shop_is_recorded()
    {
        $this->order(1);
        $this->buy(1, 8500, null, $this->toko2->id);
        $this->assertSame($this->toko2->id, (int) DB::table('purchases')->value('maktabah_id'));
    }

    public function test_mesir_cannot_correct_and_admin_cannot_access()
    {
        $this->order(1);
        $this->buy(1);
        $id = DB::table('purchases')->value('id');
        $this->actingAs($this->mesir)->put("/purchases/{$id}", ['maktabah_id' => $this->toko->id, 'qty' => 9, 'price_egp' => 1, 'rate' => 10000, 'purchased_at' => now()->toDateString()])->assertForbidden();
        $this->actingAs($this->admin)->get('/purchases')->assertForbidden();
        $this->actingAs($this->mesir)->post('/purchases/rate', ['rate' => 3000000])->assertForbidden();
    }

    public function test_owner_correction_recalculates_stock_and_order_status()
    {
        $o = $this->order(3);
        $this->buy(5); // 2 kelebihan menunggu centang gudang
        $this->assertSame(0, $this->stock());
        $this->assertSame(OrderStatus::MenungguBagasi, $o->refresh()->status);

        $id = DB::table('purchases')->value('id');
        // Ternyata hanya dapat 2 (salah ketik)
        $this->actingAs($this->owner)->put("/purchases/{$id}", [
            'maktabah_id' => $this->toko->id, 'qty' => 2, 'price_egp' => 8500, 'rate' => 3125000, 'purchased_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, $this->stock());
        $this->assertSame(OrderStatus::BelanjaMesir, $o->refresh()->status);
        $this->assertSame(1, (int) DB::table('purchase_needs')->sum(DB::raw('qty_needed - qty_fulfilled')));
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase.correct']);
    }

    public function test_correction_blocked_once_in_baggage()
    {
        $o = $this->order(1);
        $this->buy(1);
        $o->update(['status' => OrderStatus::DalamBagasi]);
        $id = DB::table('purchases')->value('id');
        $this->actingAs($this->owner)->put("/purchases/{$id}", [
            'maktabah_id' => $this->toko->id, 'qty' => 2, 'price_egp' => 8500, 'rate' => 3125000, 'purchased_at' => now()->toDateString(),
        ])->assertSessionHasErrors('qty');
    }

    public function test_mesir_only_sees_own_history_and_no_rupiah_cost()
    {
        $this->order(2);
        $this->buy(1);
        $this->buy(1, 8500, $this->owner);
        $this->actingAs($this->mesir)->get('/purchases')->assertInertia(fn (Assert $p) => $p->has('history', 1)->where('history.0.cost_idr', null));
    }

    public function test_unpaid_order_not_in_shopping_list_until_paid()
    {
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'unpaid',
            'items' => [['product_id' => $this->book->id, 'qty' => 1]],
        ]);
        $this->actingAs($this->mesir)->get('/purchases')->assertInertia(fn (Assert $p) => $p->has('groups', 0));
    }

    public function test_print_page_and_default_rate()
    {
        $this->order(2);
        $this->actingAs($this->mesir)->get('/purchases/print')->assertOk();
        $this->actingAs($this->owner)->post('/purchases/rate', ['rate' => 3200000])->assertSessionHasNoErrors();
        $this->actingAs($this->mesir)->get('/purchases')->assertInertia(fn (Assert $p) => $p->where('defaultRate', 3200000));
    }
}
