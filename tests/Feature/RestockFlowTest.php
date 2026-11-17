<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Maktabah;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestockFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $mesir;

    private User $gudang;

    private User $admin;

    private Product $p;

    private Maktabah $toko;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->mesir = User::factory()->role(Role::Mesir)->create();
        $this->gudang = User::factory()->role(Role::Gudang)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $this->p = Product::create(['title_latin' => 'Arbain', 'weight_grams' => 200, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 2000, 'price_regular' => 70000, 'price_reseller' => 60000]);
    }

    private function stock(): int
    {
        return (int) DB::table('product_stock')->where('product_id', $this->p->id)->value('qty');
    }

    private function buy(int $qty): void
    {
        $this->actingAs($this->mesir)->post('/purchases', [
            'product_id' => $this->p->id, 'maktabah_id' => $this->toko->id, 'qty' => $qty,
            'price_egp' => 2000, 'rate' => 3000000, 'purchased_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
    }

    public function test_only_owner_adds_restock_and_mesir_sees_it()
    {
        $this->actingAs($this->mesir)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 5])->assertForbidden();
        $this->actingAs($this->owner)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 5])->assertSessionHasNoErrors();

        $this->actingAs($this->mesir)->get('/purchases')->assertInertia(fn ($p) => $p->has('restocks', 1)->where('restocks.0.qty', 5));
    }

    public function test_restock_is_filled_and_stock_updates_only_after_warehouse_checks()
    {
        $this->actingAs($this->owner)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 5]);

        $this->buy(3);
        $this->assertSame(0, $this->stock(), 'stok belum bertambah sebelum dicentang gudang');
        $this->assertSame(3, (int) DB::table('purchase_arrivals')->where('source', 'restok')->value('qty'));
        $this->assertSame(3, (int) DB::table('restock_needs')->value('qty_fulfilled'));

        $arrival = DB::table('purchase_arrivals')->value('id');
        $this->actingAs($this->gudang)->post("/warehouse/arrivals/{$arrival}/receive")->assertSessionHasNoErrors();
        $this->assertSame(3, $this->stock());
        $this->assertSame(2, (int) DB::table('restock_needs')->value('qty_needed') - (int) DB::table('restock_needs')->value('qty_fulfilled'));
    }

    public function test_order_needs_come_before_restock()
    {
        $this->actingAs($this->owner)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 5]);
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => \App\Models\Customer::create(['name' => 'A', 'type' => 'reguler'])->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $this->p->id, 'qty' => 2]],
        ])->assertSessionHasNoErrors();

        $this->buy(4); // 2 untuk pesanan, 2 untuk restok, 0 kelebihan
        $this->assertSame(2, (int) DB::table('purchase_arrivals')->where('source', 'restok')->value('qty'));
        $this->assertSame(0, DB::table('purchase_arrivals')->where('source', 'lebih')->count());
    }

    public function test_correction_before_check_reverts_restock_progress()
    {
        $this->actingAs($this->owner)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 5]);
        $this->buy(3);
        $id = DB::table('purchases')->value('id');

        $this->actingAs($this->owner)->put("/purchases/{$id}", [
            'maktabah_id' => $this->toko->id, 'qty' => 1, 'price_egp' => 2000, 'rate' => 3000000, 'purchased_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, (int) DB::table('restock_needs')->value('qty_fulfilled'));
        $this->assertSame(1, (int) DB::table('purchase_arrivals')->value('qty'));
    }

    public function test_restock_with_progress_cannot_be_cancelled()
    {
        $this->actingAs($this->owner)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 5]);
        $this->buy(1);
        $id = DB::table('restock_needs')->value('id');
        $this->actingAs($this->owner)->post("/purchases/restock/{$id}/cancel")->assertSessionHasErrors('restock');
    }

    public function test_warehouse_checks_all_arrivals_at_once()
    {
        $this->actingAs($this->owner)->post('/purchases/restock', ['product_id' => $this->p->id, 'qty' => 10]);
        $this->buy(4);
        $this->actingAs($this->gudang)->post('/warehouse/arrivals/receive-all')->assertSessionHasNoErrors();
        $this->assertSame(4, $this->stock());
        $this->assertSame(0, DB::table('purchase_arrivals')->whereNull('received_at')->count());
    }
}
