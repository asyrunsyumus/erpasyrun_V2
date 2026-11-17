<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Maktabah;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BaggageFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $mesir;

    private Product $heavy; // 1000 g

    private Product $light; // 200 g

    private Customer $cust;

    private Maktabah $toko;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->mesir = User::factory()->role(Role::Mesir)->create();
        $this->toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $this->heavy = Product::create(['title_latin' => 'Tafsir', 'weight_grams' => 1000, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 10000, 'price_regular' => 300000, 'price_reseller' => 280000]);
        $this->light = Product::create(['title_latin' => 'Arbain', 'weight_grams' => 200, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 2000, 'price_regular' => 70000, 'price_reseller' => 60000]);
        $this->cust = Customer::create(['name' => 'Ahmad', 'type' => 'reguler']);
    }

    /** Buat order lunas lalu belikan semua kitabnya → status Menunggu bagasi. */
    private function readyOrder(Product $p, int $qty): Order
    {
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $p->id, 'qty' => $qty]],
        ])->assertSessionHasNoErrors();
        $o = Order::latest('id')->first();
        $this->actingAs($this->mesir)->post('/purchases', [
            'product_id' => $p->id, 'maktabah_id' => $this->toko->id, 'qty' => $qty,
            'price_egp' => $p->price_egp, 'rate' => 3000000, 'purchased_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->travel(1)->minutes();

        return $o->refresh();
    }

    private function schedule(?float $kg, int $cost = 0): int
    {
        $this->actingAs($this->owner)->post('/baggage', [
            'depart_date' => now()->addDays(7)->toDateString(), 'carrier_contact' => 'Ust. Hamid', 'quota_kg' => $kg, 'cost_idr' => $cost,
        ])->assertSessionHasNoErrors();

        return (int) DB::table('baggage_schedules')->max('id');
    }

    public function test_only_owner_can_manage_baggage()
    {
        foreach ([$this->admin, $this->mesir, User::factory()->role(Role::Gudang)->create()] as $u) {
            $this->actingAs($u)->get('/baggage')->assertForbidden();
            $this->actingAs($u)->post('/baggage', ['depart_date' => now()->toDateString(), 'carrier_contact' => 'x'])->assertForbidden();
        }
    }

    public function test_creating_schedule_assigns_waiting_orders_oldest_first_within_quota()
    {
        $a = $this->readyOrder($this->heavy, 2); // 2 kg, paling lama
        $b = $this->readyOrder($this->heavy, 2); // 2 kg
        $c = $this->readyOrder($this->light, 1); // 0,2 kg

        $sid = $this->schedule(3); // muat a (2) lalu b tidak muat (4) → c (2,2) muat

        $this->assertSame(OrderStatus::DalamBagasi, $a->refresh()->status);
        $this->assertSame(OrderStatus::MenungguBagasi, $b->refresh()->status);
        $this->assertSame(OrderStatus::DalamBagasi, $c->refresh()->status);
        $this->assertSame(2200, (int) DB::table('baggage_loads')->where('baggage_schedule_id', $sid)->sum('weight_grams'));
    }

    public function test_empty_quota_means_unlimited()
    {
        $this->readyOrder($this->heavy, 50);
        $this->schedule(null);
        $this->assertSame(1, DB::table('baggage_loads')->count());
    }

    public function test_raising_quota_rechecks_waiting_orders()
    {
        $a = $this->readyOrder($this->heavy, 2);
        $b = $this->readyOrder($this->heavy, 2);
        $sid = $this->schedule(2);
        $this->assertSame(OrderStatus::MenungguBagasi, $b->refresh()->status);

        $this->actingAs($this->owner)->put("/baggage/{$sid}", [
            'depart_date' => now()->addDays(7)->toDateString(), 'carrier_contact' => 'Ust. Hamid', 'quota_kg' => 4,
        ])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::DalamBagasi, $b->refresh()->status);
    }

    public function test_quota_cannot_go_below_used()
    {
        $this->readyOrder($this->heavy, 2);
        $sid = $this->schedule(5);
        $this->actingAs($this->owner)->put("/baggage/{$sid}", [
            'depart_date' => now()->addDays(7)->toDateString(), 'carrier_contact' => 'x', 'quota_kg' => 1,
        ])->assertSessionHasErrors('quota_kg');
    }

    public function test_items_from_stock_do_not_count_toward_weight()
    {
        app(StockLedger::class)->move($this->heavy->id, 3, 'import_awal', null, 100000);
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $this->heavy->id, 'qty' => 4]], // 3 gudang + 1 beli
        ]);
        $this->actingAs($this->mesir)->post('/purchases', ['product_id' => $this->heavy->id, 'maktabah_id' => $this->toko->id, 'qty' => 1, 'price_egp' => 10000, 'rate' => 3000000, 'purchased_at' => now()->toDateString()]);
        $this->schedule(10);
        $this->assertSame(1000, (int) DB::table('baggage_loads')->value('weight_grams'));
        $this->assertSame(1, (int) DB::table('inbound_lines')->value('qty'));
    }

    /** Masalah lama #4: ongkos bagasi masuk HPP. */
    public function test_baggage_cost_is_allocated_into_unit_cost_by_weight()
    {
        $a = $this->readyOrder($this->heavy, 1); // 1000 g; beli 100 EGP × 300 = 30.000
        $b = $this->readyOrder($this->light, 1); // 200 g;  beli 20 EGP × 300 = 6.000
        $this->schedule(null, 120000); // 120.000 / 1200 g = 100 / g

        $this->assertSame(30000 + 100000, (int) DB::table('order_items')->where('order_id', $a->id)->value('unit_cost'));
        $this->assertSame(6000 + 20000, (int) DB::table('order_items')->where('order_id', $b->id)->value('unit_cost'));

        // Owner melihat untung pesanan
        $this->actingAs($this->owner)->get("/orders/{$a->id}")->assertInertia(fn (Assert $p) => $p->where('cost.cogs', 130000)->where('cost.profit', 300000 - 130000));
    }

    public function test_changing_cost_recomputes_unit_cost()
    {
        $a = $this->readyOrder($this->heavy, 1);
        $sid = $this->schedule(null, 0);
        $this->assertSame(30000, (int) DB::table('order_items')->where('order_id', $a->id)->value('unit_cost'));
        $this->actingAs($this->owner)->put("/baggage/{$sid}", ['depart_date' => now()->addDays(7)->toDateString(), 'carrier_contact' => 'x', 'quota_kg' => null, 'cost_idr' => 50000]);
        $this->assertSame(80000, (int) DB::table('order_items')->where('order_id', $a->id)->value('unit_cost'));
    }

    public function test_all_from_stock_order_has_final_cost_immediately()
    {
        app(StockLedger::class)->move($this->light->id, 5, 'import_awal', null, 25000);
        $this->actingAs($this->admin)->post('/orders', ['customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid', 'items' => [['product_id' => $this->light->id, 'qty' => 2]]]);
        $this->assertSame(25000, (int) DB::table('order_items')->value('unit_cost'));
    }

    public function test_delete_blocked_while_used_then_move_then_delete()
    {
        $a = $this->readyOrder($this->heavy, 1);
        $s1 = $this->schedule(null);
        $s2 = $this->schedule(5);

        $this->actingAs($this->owner)->delete("/baggage/{$s1}")->assertSessionHasErrors('delete');

        $this->actingAs($this->owner)->post("/baggage/{$s1}/move", ['target_id' => $s2])->assertSessionHasNoErrors();
        $this->assertSame($s2, (int) DB::table('baggage_loads')->where('order_id', $a->id)->value('baggage_schedule_id'));
        $this->assertSame(1, (int) DB::table('inbound_lines')->where('baggage_schedule_id', $s2)->value('qty'));
        $this->assertSame(0, DB::table('inbound_lines')->where('baggage_schedule_id', $s1)->count());

        $this->actingAs($this->owner)->delete("/baggage/{$s1}")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('baggage_schedules', ['id' => $s1]);
    }

    public function test_move_rejected_if_target_quota_too_small()
    {
        $this->readyOrder($this->heavy, 3);
        $s1 = $this->schedule(null);
        $s2 = $this->schedule(1);
        $this->actingAs($this->owner)->post("/baggage/{$s1}/move", ['target_id' => $s2])->assertSessionHasErrors('target_id');
    }

    public function test_baggage_page_lists_waiting_and_usage()
    {
        $this->readyOrder($this->heavy, 1);
        $this->actingAs($this->owner)->get('/baggage')->assertInertia(fn (Assert $p) => $p->has('waiting', 1)->where('waitingGrams', 1000));
        $this->schedule(2);
        $this->actingAs($this->owner)->get('/baggage')->assertInertia(fn (Assert $p) => $p->has('waiting', 0)->where('schedules.0.used_grams', 1000));
    }
}
