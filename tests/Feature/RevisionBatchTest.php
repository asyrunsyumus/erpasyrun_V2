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
use Tests\TestCase;

class RevisionBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private User $gudang;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->gudang = User::factory()->role(Role::Gudang)->create();
    }

    /** Pesanan yang sudah masuk tahap packing, dengan satu kitab. */
    private function packingOrder(): int
    {
        $toko = Maktabah::create(['name' => 'Dar al-Salam']);
        $p = Product::create(['title_latin' => 'Arbain', 'weight_grams' => 200, 'primary_maktabah_id' => $toko->id, 'price_egp' => 2000, 'price_regular' => 70000, 'price_reseller' => 60000]);
        $cust = Customer::create(['name' => 'Zunnay', 'type' => 'reguler']);
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $cust->id, 'channel' => 'whatsapp', 'payment_status' => 'paid',
            'items' => [['product_id' => $p->id, 'qty' => 1]],
        ])->assertSessionHasNoErrors();
        $order = Order::latest('id')->first();
        $order->update(['status' => OrderStatus::SiapPacking]);

        return $order->id;
    }

    public function test_packing_check_cannot_be_unchecked()
    {
        $orderId = $this->packingOrder();
        $item = DB::table('order_items')->where('order_id', $orderId)->value('id');

        $this->actingAs($this->gudang)->post("/warehouse/items/{$item}/check", ['checked' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('packing_checks')->where('order_item_id', $item)->count());

        $this->actingAs($this->gudang)->post("/warehouse/items/{$item}/check", ['checked' => false])->assertSessionHasErrors('line');
        $this->assertSame(1, DB::table('packing_checks')->where('order_item_id', $item)->count());
    }

    public function test_owner_sees_warehouse_progress_and_gudang_does_not()
    {
        $this->packingOrder();
        $this->actingAs($this->owner)->get('/warehouse-progress')->assertOk();
        $this->actingAs($this->gudang)->get('/warehouse-progress')->assertForbidden();
        $this->actingAs($this->admin)->get('/warehouse-progress')->assertForbidden();
    }

    public function test_maktabah_maps_link_is_saved_manually_without_confirmation()
    {
        $this->actingAs($this->owner)->post('/maktabahs', [
            'name' => 'Dar Ibn Hazm', 'gmaps_url' => 'https://maps.app.goo.gl/abc',
        ])->assertSessionHasNoErrors();
        $this->assertSame('https://maps.app.goo.gl/abc', Maktabah::first()->gmaps_url);
    }

    public function test_maktabah_maps_link_must_be_http_url_and_can_be_empty()
    {
        $this->actingAs($this->owner)->post('/maktabahs', ['name' => 'Dar Ibn Hazm', 'gmaps_url' => 'bukan-link'])->assertSessionHasErrors('gmaps_url');
        $this->actingAs($this->owner)->post('/maktabahs', ['name' => 'Dar Ibn Hazm'])->assertSessionHasNoErrors();
        $m = Maktabah::first();
        $this->actingAs($this->owner)->put("/maktabahs/{$m->id}", ['name' => 'Dar Ibn Hazm', 'gmaps_url' => ''])->assertSessionHasNoErrors();
        $this->assertNull($m->refresh()->gmaps_url);
    }

    public function test_customer_address_fields_are_saved()
    {
        $this->actingAs($this->admin)->post('/customers', [
            'name' => 'Ahmad', 'type' => 'reguler', 'city' => 'Kota Cairo',
            'kelurahan' => 'Nasr City', 'kecamatan' => 'Madinat Nasr', 'kode_pos' => '11765',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('customers', ['name' => 'Ahmad', 'kelurahan' => 'Nasr City', 'kecamatan' => 'Madinat Nasr', 'kode_pos' => '11765']);
    }

    public function test_product_pentahqiq_is_saved()
    {
        $this->actingAs($this->owner)->post('/products', [
            'title_latin' => 'Riyadhus Shalihin', 'pentahqiq' => 'Syaikh Syu’aib al-Arna’ūṭ',
            'price_egp' => 1000, 'price_regular' => 50000, 'price_reseller' => 45000,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['title_latin' => 'Riyadhus Shalihin', 'pentahqiq' => 'Syaikh Syu’aib al-Arna’ūṭ']);
    }
}
