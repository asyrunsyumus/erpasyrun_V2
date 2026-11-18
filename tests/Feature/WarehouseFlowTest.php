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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WarehouseFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $mesir;

    private User $gudang;

    private Product $a;

    private Product $b;

    private Customer $cust;

    private Maktabah $toko;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->mesir = User::factory()->role(Role::Mesir)->create();
        $this->gudang = User::factory()->role(Role::Gudang)->create();
        $this->toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $this->a = Product::create(['title_latin' => 'Fathul Qarib', 'weight_grams' => 400, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 8500, 'price_regular' => 150000, 'price_reseller' => 135000]);
        $this->b = Product::create(['title_latin' => 'Arbain', 'weight_grams' => 200, 'primary_maktabah_id' => $this->toko->id, 'price_egp' => 2000, 'price_regular' => 70000, 'price_reseller' => 60000]);
        $this->cust = Customer::create(['name' => 'Ahmad', 'type' => 'reguler', 'city' => 'Jombang']);
    }

    /** Order 2 kitab → dibeli → dijadwalkan → status Dalam perjalanan. */
    private function inTransit(): Order
    {
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $this->a->id, 'qty' => 2], ['product_id' => $this->b->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $o = Order::latest('id')->first();
        foreach ([[$this->a, 2], [$this->b, 1]] as [$p, $q]) {
            $this->actingAs($this->mesir)->post('/purchases', ['product_id' => $p->id, 'maktabah_id' => $this->toko->id, 'qty' => $q, 'price_egp' => $p->price_egp, 'rate' => 3000000, 'purchased_at' => now()->toDateString()]);
        }
        $this->actingAs($this->owner)->post('/baggage', ['depart_date' => now()->toDateString(), 'carrier_contact' => 'Hamid', 'quota_kg' => null]);

        return $o->refresh();
    }

    private function label(Order $o): void
    {
        $this->actingAs($this->admin)->post("/orders/{$o->id}/label", ['file' => UploadedFile::fake()->create('resi.pdf', 20, 'application/pdf')]);
    }

    public function test_only_gudang_can_open_warehouse_even_not_owner()
    {
        $this->actingAs($this->gudang)->get('/warehouse')->assertOk();
        foreach ([$this->owner, $this->admin, $this->mesir] as $u) {
            $this->actingAs($u)->get('/warehouse')->assertForbidden();
        }
        $o = $this->inTransit();
        $line = DB::table('inbound_lines')->value('id');
        $this->actingAs($this->owner)->post("/warehouse/inbound/{$line}/receive")->assertForbidden();
        $this->assertSame(OrderStatus::DalamBagasi, $o->refresh()->status);
    }

    public function test_order_ready_only_after_all_lines_received()
    {
        $o = $this->inTransit();
        $this->assertSame(OrderStatus::DalamBagasi, $o->status);
        $lines = DB::table('inbound_lines')->orderBy('id')->pluck('id');
        $this->assertCount(2, $lines);

        $this->actingAs($this->gudang)->post("/warehouse/inbound/{$lines[0]}/receive")->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::DalamBagasi, $o->refresh()->status);

        $this->actingAs($this->gudang)->post("/warehouse/inbound/{$lines[1]}/receive")->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::SiapPacking, $o->refresh()->status);

        // Tidak bisa dikonfirmasi dua kali
        $this->actingAs($this->gudang)->post("/warehouse/inbound/{$lines[1]}/receive")->assertSessionHasErrors('line');
    }

    public function test_finish_requires_all_checks_and_label()
    {
        $o = $this->inTransit();
        foreach (DB::table('inbound_lines')->pluck('id') as $id) {
            $this->actingAs($this->gudang)->post("/warehouse/inbound/{$id}/receive");
        }
        $items = DB::table('order_items')->where('order_id', $o->id)->pluck('id');

        $this->actingAs($this->gudang)->post("/warehouse/orders/{$o->id}/finish")->assertSessionHasErrors('finish');

        foreach ($items as $i) {
            $this->actingAs($this->gudang)->post("/warehouse/items/{$i}/check", ['checked' => true]);
        }
        $this->actingAs($this->gudang)->post("/warehouse/orders/{$o->id}/finish")->assertSessionHasErrors('finish'); // belum ada resi

        $this->label($o);
        $this->actingAs($this->gudang)->post("/warehouse/orders/{$o->id}/finish")->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::Selesai, $o->refresh()->status);

        // Riwayat nyata: tanggal terima & selesai tercatat terpisah (masalah lama "tanggal selalu sama")
        $notes = DB::table('order_status_histories')->where('order_id', $o->id)->pluck('to_status')->all();
        $this->assertContains('siap_packing', $notes);
        $this->assertContains('selesai', $notes);
    }

    public function test_checked_packing_cannot_be_unchecked_and_owner_cannot_finish()
    {
        $o = $this->inTransit();
        foreach (DB::table('inbound_lines')->pluck('id') as $id) {
            $this->actingAs($this->gudang)->post("/warehouse/inbound/{$id}/receive");
        }
        $item = DB::table('order_items')->where('order_id', $o->id)->value('id');
        $this->actingAs($this->gudang)->post("/warehouse/items/{$item}/check", ['checked' => true]);
        $this->actingAs($this->gudang)->post("/warehouse/items/{$item}/check", ['checked' => false])->assertSessionHasErrors('line');
        $this->assertSame(1, DB::table('packing_checks')->count());
        $this->actingAs($this->owner)->post("/warehouse/orders/{$o->id}/finish")->assertForbidden();
    }

    public function test_packing_tab_groups_by_customer_without_prices()
    {
        $o = $this->inTransit();
        foreach (DB::table('inbound_lines')->pluck('id') as $id) {
            $this->actingAs($this->gudang)->post("/warehouse/inbound/{$id}/receive");
        }
        $res = $this->actingAs($this->gudang)->get('/warehouse');
        $res->assertInertia(fn (Assert $p) => $p->where('groups.0.customer', 'Ahmad')->has('groups.0.orders.0.items', 2)->missing('groups.0.orders.0.total'));
        $this->assertStringNotContainsString('150000', $res->getContent());
    }

    // ---------- Stok Ready & opname ----------

    public function test_stock_page_roles_and_no_cost_data()
    {
        app(StockLedger::class)->move($this->a->id, 5, 'import_awal', null, 99999);
        foreach ([$this->owner, $this->admin, $this->gudang] as $u) {
            $res = $this->actingAs($u)->get('/stock')->assertOk();
            $this->assertStringNotContainsString('99999', $res->getContent());
        }
        $this->actingAs($this->mesir)->get('/stock')->assertForbidden();
        $this->actingAs($this->gudang)->get('/stock/print')->assertOk();
    }

    public function test_opname_changes_stock_only_after_owner_approves()
    {
        app(StockLedger::class)->move($this->a->id, 10, 'import_awal', null, 0);
        $this->actingAs($this->gudang)->post('/stock/counts', ['lines' => [['product_id' => $this->a->id, 'counted_qty' => 7]], 'note' => 'hitung ulang'])->assertSessionHasNoErrors();
        $this->assertSame(10, (int) DB::table('product_stock')->value('qty'));

        $this->actingAs($this->admin)->post('/stock/counts', ['lines' => [['product_id' => $this->a->id, 'counted_qty' => 1]]])->assertForbidden();

        $id = DB::table('stock_counts')->value('id');
        $this->actingAs($this->gudang)->post("/stock/counts/{$id}/decide", ['approve' => 1])->assertForbidden();
        $this->actingAs($this->owner)->post("/stock/counts/{$id}/decide", ['approve' => 1])->assertSessionHasNoErrors();

        $this->assertSame(7, (int) DB::table('product_stock')->value('qty'));
        $this->assertSame(-3, (int) DB::table('stock_movements')->where('type', 'opname')->value('qty'));

        // Tidak bisa diputuskan dua kali
        $this->actingAs($this->owner)->post("/stock/counts/{$id}/decide", ['approve' => 1])->assertSessionHasErrors('count');
    }

    public function test_rejected_opname_leaves_stock()
    {
        app(StockLedger::class)->move($this->a->id, 10, 'import_awal', null, 0);
        $this->actingAs($this->gudang)->post('/stock/counts', ['lines' => [['product_id' => $this->a->id, 'counted_qty' => 2]]]);
        $id = DB::table('stock_counts')->value('id');
        $this->actingAs($this->owner)->post("/stock/counts/{$id}/decide", ['approve' => 0]);
        $this->assertSame(10, (int) DB::table('product_stock')->value('qty'));
    }

    public function test_dashboard_tasks_for_gudang()
    {
        $this->inTransit();
        $this->actingAs($this->gudang)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('tasks.0.count', 2));
    }
}
