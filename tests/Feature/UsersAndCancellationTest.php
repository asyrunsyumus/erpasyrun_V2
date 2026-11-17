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
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UsersAndCancellationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
    }

    // ---------------- Pengguna ----------------

    public function test_owner_creates_staff_with_one_time_temp_password()
    {
        $res = $this->actingAs($this->owner)->post('/users', ['name' => 'Yusuf', 'email' => 'yusuf@contoh.com', 'role' => 'mesir'])->assertSessionHasNoErrors();
        $temp = session('temp_password')['password'];
        $u = User::firstWhere('email', 'yusuf@contoh.com');

        $this->assertSame(Role::Mesir, $u->role);
        $this->assertTrue($u->must_change_password);
        $this->assertTrue(Hash::check($temp, $u->password));
        $this->assertDatabaseMissing('audit_logs', ['after' => json_encode(['password' => $temp])]);

        // Tampil di halaman tujuan redirect, lalu tidak pernah tampil lagi
        $this->actingAs($this->owner)->get('/users')->assertInertia(fn (Assert $p) => $p->where('flash.temp_password.password', $temp));
        $this->actingAs($this->owner)->get('/users')->assertInertia(fn (Assert $p) => $p->where('flash.temp_password', null));
    }

    public function test_non_owner_cannot_manage_users()
    {
        $this->actingAs($this->admin)->get('/users')->assertForbidden();
        $this->actingAs($this->admin)->post('/users', ['name' => 'x', 'email' => 'x@x.com', 'role' => 'owner'])->assertForbidden();
    }

    public function test_deactivate_kicks_user_out()
    {
        $staff = User::factory()->role(Role::Gudang)->create();
        $this->actingAs($this->owner)->post("/users/{$staff->id}/toggle")->assertSessionHasNoErrors();
        $this->assertFalse($staff->refresh()->is_active);
        $this->actingAs($staff)->get('/dashboard')->assertRedirect('/login');
    }

    public function test_owner_cannot_deactivate_or_delete_self()
    {
        $this->actingAs($this->owner)->post("/users/{$this->owner->id}/toggle")->assertStatus(422);
        $this->actingAs($this->owner)->delete("/users/{$this->owner->id}", ['password' => 'password'])->assertStatus(422);
    }

    public function test_reset_password_gives_new_temp()
    {
        $staff = User::factory()->role(Role::Admin)->create();
        $this->actingAs($this->owner)->post("/users/{$staff->id}/reset-password");
        $this->assertTrue(Hash::check(session('temp_password')['password'], $staff->refresh()->password));
        $this->assertTrue($staff->must_change_password);
    }

    public function test_delete_requires_password_and_keeps_history()
    {
        $staff = User::factory()->role(Role::Admin)->create(['name' => 'Rina', 'email' => 'rina@contoh.com']);
        $c = Customer::create(['name' => 'A', 'type' => 'reguler']);
        $orderId = DB::table('orders')->insertGetId(['number' => 'T1', 'customer_id' => $c->id, 'channel' => 'whatsapp', 'payment_status' => 'paid', 'status' => 'selesai', 'subtotal' => 1, 'total' => 1, 'created_by' => $staff->id]);

        $this->actingAs($this->owner)->delete("/users/{$staff->id}", ['password' => 'salah'])->assertSessionHasErrors('password');
        $this->actingAs($this->owner)->delete("/users/{$staff->id}", ['password' => 'password'])->assertSessionHasNoErrors();

        $staff->refresh();
        $this->assertSame('Pengguna dihapus', $staff->name);
        $this->assertStringNotContainsString('rina', $staff->email);
        $this->assertFalse($staff->is_active);
        $this->assertDatabaseHas('orders', ['id' => $orderId, 'created_by' => $staff->id]);
        auth()->logout();
        $this->post('/login', ['email' => 'rina@contoh.com', 'password' => 'password'])->assertSessionHasErrors('email');
    }

    // ---------------- Pembatalan ----------------

    private function setupOrder(): array
    {
        $mesir = User::factory()->role(Role::Mesir)->create();
        $toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $p = Product::create(['title_latin' => 'Fathul Qarib', 'weight_grams' => 400, 'primary_maktabah_id' => $toko->id, 'price_egp' => 8500, 'price_regular' => 150000, 'price_reseller' => 135000]);
        app(StockLedger::class)->move($p->id, 2, 'import_awal', null, 20000);
        $c = Customer::create(['name' => 'Ahmad', 'type' => 'reguler']);
        // 5 dipesan: 2 dari gudang, 3 dibeli; Tim Mesir baru dapat 1
        $this->actingAs($this->admin)->post('/orders', ['customer_id' => $c->id, 'channel' => 'whatsapp', 'payment_status' => 'paid', 'items' => [['product_id' => $p->id, 'qty' => 5]]])->assertSessionHasNoErrors();
        $o = Order::latest('id')->first();
        $this->actingAs($mesir)->post('/purchases', ['product_id' => $p->id, 'maktabah_id' => $toko->id, 'qty' => 1, 'price_egp' => 8500, 'rate' => 3000000, 'purchased_at' => now()->toDateString()]);

        return [$o, $p];
    }

    private function stock(Product $p): int
    {
        return (int) DB::table('product_stock')->where('product_id', $p->id)->value('qty');
    }

    public function test_request_then_reject_restores_status_and_allows_new_request()
    {
        [$o] = $this->setupOrder();
        $this->actingAs($this->admin)->post("/orders/{$o->id}/cancel", ['reason' => 'pembeli berubah pikiran'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::MenungguPembatalan, $o->refresh()->status);
        $this->assertSame(1, $this->owner->notifications()->where('type', \App\Notifications\CancellationRequested::class)->count());

        // Tidak bisa dobel pengajuan selagi menunggu
        $this->actingAs($this->admin)->post("/orders/{$o->id}/cancel", ['reason' => 'lagi dong'])->assertSessionHasErrors('reason');

        $cid = DB::table('cancellations')->value('id');
        $this->actingAs($this->owner)->post("/cancellations/{$cid}/reject", ['note' => 'dipikir dulu'])->assertSessionHasNoErrors();
        $this->assertSame(OrderStatus::BelanjaMesir, $o->refresh()->status);

        // Keputusan Owner: boleh diajukan lagi
        $this->actingAs($this->admin)->post("/orders/{$o->id}/cancel", ['reason' => 'pembeli tetap batal'])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('cancellations')->count());
    }

    public function test_approve_returns_stock_from_warehouse_and_purchased_and_closes_needs()
    {
        [$o, $p] = $this->setupOrder();
        $this->assertSame(0, $this->stock($p));

        $this->actingAs($this->admin)->post("/orders/{$o->id}/cancel", ['reason' => 'pembeli batal']);
        $cid = DB::table('cancellations')->value('id');
        // Yang mengajukan boleh juga menyetujui (PRD)
        $this->actingAs($this->admin)->post("/cancellations/{$cid}/approve", ['refund_amount' => 750000, 'refund_method' => 'transfer BSI'])->assertSessionHasNoErrors();

        $this->assertSame(OrderStatus::Dibatalkan, $o->refresh()->status);
        $this->assertSame(3, $this->stock($p)); // 2 dari gudang + 1 yang sudah dibeli
        $this->assertSame(0, DB::table('purchase_needs')->where('status', 'open')->count());
        $this->assertSame(750000, (int) DB::table('cancellations')->value('refund_amount'));

        // Penjualan bulan ini tidak menghitung pesanan batal; uang masuk dikurangi refund
        $this->actingAs($this->owner)->get('/dashboard')->assertInertia(fn (Assert $pg) => $pg->where('kpis.sales.value', 0)->where('kpis.cash.value', 0));

        // Tidak bisa diputuskan dua kali
        $this->actingAs($this->owner)->post("/cancellations/{$cid}/approve", ['refund_amount' => 0])->assertSessionHasErrors('decision');
    }

    public function test_refund_cannot_exceed_total_and_method_required()
    {
        [$o] = $this->setupOrder();
        $this->actingAs($this->admin)->post("/orders/{$o->id}/cancel", ['reason' => 'pembeli batal']);
        $cid = DB::table('cancellations')->value('id');
        $this->actingAs($this->owner)->post("/cancellations/{$cid}/approve", ['refund_amount' => 1])->assertSessionHasErrors('refund_method');
        $this->actingAs($this->owner)->post("/cancellations/{$cid}/approve", ['refund_amount' => 99999999, 'refund_method' => 'x'])->assertSessionHasErrors('refund_amount');
        $this->assertSame(OrderStatus::MenungguPembatalan, $o->refresh()->status);
    }

    public function test_finished_order_cannot_be_cancelled_and_others_cannot_access()
    {
        [$o] = $this->setupOrder();
        $o->update(['status' => OrderStatus::Selesai]);
        $this->actingAs($this->admin)->post("/orders/{$o->id}/cancel", ['reason' => 'terlambat'])->assertSessionHasErrors('reason');
        $this->actingAs(User::factory()->role(Role::Gudang)->create())->get('/cancellations')->assertForbidden();
    }

    // ---------------- Notifikasi per orang ----------------

    public function test_notifications_are_per_person()
    {
        $admin2 = User::factory()->role(Role::Admin)->create();
        [$o] = $this->setupOrder();
        $this->actingAs($this->owner)->post("/orders/{$o->id}/cancel", ['reason' => 'pembeli batal']);

        $this->assertSame(1, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $admin2->unreadNotifications()->count());

        $id = $this->admin->notifications()->first()->id;
        $this->actingAs($this->admin)->get("/notifications/{$id}/open")->assertRedirect('/cancellations');

        $this->assertSame(0, $this->admin->unreadNotifications()->count());
        $this->assertSame(1, $admin2->unreadNotifications()->count()); // tidak ikut hilang
        // Tidak bisa membuka notifikasi milik orang lain
        $other = $admin2->notifications()->first()->id;
        $this->actingAs($this->admin)->get("/notifications/{$other}/open")->assertNotFound();
    }
}
