<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Maktabah;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BaggageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BaggageMatchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $mesir;

    private User $gudang;

    private Product $p;

    private Customer $c;

    private Maktabah $toko;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->mesir = User::factory()->role(Role::Mesir)->create();
        $this->gudang = User::factory()->role(Role::Gudang)->create();
        $this->toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $this->p = Product::create(['title_latin' => 'Tafsir', 'weight_grams' => 1000, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 10000, 'price_regular' => 300000, 'price_reseller' => 280000]);
        $this->c = Customer::create(['name' => 'Ahmad', 'type' => 'reguler']);
    }

    private function schedule(int $daysAhead, ?float $kg): int
    {
        $this->actingAs($this->owner)->post('/baggage', [
            'depart_date' => now()->addDays($daysAhead)->toDateString(), 'carrier_contact' => 'Hamid', 'quota_kg' => $kg,
        ])->assertSessionHasNoErrors();

        return (int) DB::table('baggage_schedules')->max('id');
    }

    /** Pesan 1 kitab lalu belikan di Mesir → pesanan menunggu bagasi (tanpa jadwal dulu). */
    private function purchasedOrder(): Order
    {
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->c->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $this->p->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $o = Order::latest('id')->first();
        $this->actingAs($this->mesir)->post('/purchases', [
            'product_id' => $this->p->id, 'maktabah_id' => $this->toko->id, 'qty' => 1,
            'price_egp' => 10000, 'rate' => 3000000, 'purchased_at' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        return $o->refresh();
    }

    public function test_order_finishing_shopping_is_matched_to_nearest_schedule_with_quota()
    {
        $far = $this->schedule(20, null);
        $near = $this->schedule(3, 2.5);

        $o = $this->purchasedOrder();

        $this->assertSame(OrderStatus::DalamBagasi, $o->status);
        $this->assertSame($near, (int) DB::table('baggage_loads')->where('order_id', $o->id)->value('baggage_schedule_id'));
        $this->assertNotSame($far, (int) DB::table('baggage_loads')->where('order_id', $o->id)->value('baggage_schedule_id'));
    }

    public function test_full_nearest_schedule_falls_through_to_next()
    {
        $near = $this->schedule(3, 0.5); // 1 kg tidak muat
        $far = $this->schedule(20, null);

        $o = $this->purchasedOrder();

        $this->assertSame($far, (int) DB::table('baggage_loads')->where('order_id', $o->id)->value('baggage_schedule_id'));
        $this->assertSame(0, DB::table('baggage_loads')->where('baggage_schedule_id', $near)->count());
    }

    public function test_past_schedules_are_ignored()
    {
        $this->schedule(-2, null);
        $o = $this->purchasedOrder();
        $this->assertSame(OrderStatus::MenungguBagasi, $o->status);
    }

    public function test_owner_manual_match_only_owner()
    {
        $o = $this->purchasedOrder(); // belum ada jadwal → tetap menunggu
        $this->schedule(5, null);

        $this->actingAs($this->admin)->post('/baggage/match')->assertForbidden();
        $this->actingAs($this->owner)->post('/baggage/match')->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::DalamBagasi, $o->refresh()->status);
    }

    public function test_warehouse_checks_whole_schedule_at_once()
    {
        $sid = $this->schedule(2, null);
        $this->purchasedOrder();
        $this->purchasedOrder();
        $this->assertGreaterThan(0, DB::table('inbound_lines')->where('baggage_schedule_id', $sid)->whereNull('received_at')->count());

        $this->actingAs($this->gudang)->post("/warehouse/schedules/{$sid}/receive-all")->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('inbound_lines')->where('baggage_schedule_id', $sid)->whereNull('received_at')->count());
        $this->assertSame(2, Order::where('status', OrderStatus::SiapPacking)->count());
    }

    public function test_matchWaiting_is_idempotent()
    {
        $this->schedule(3, null);
        $this->purchasedOrder();
        $this->assertSame([], app(BaggageService::class)->matchWaiting($this->owner));
    }
}
