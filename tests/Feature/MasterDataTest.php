<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Maktabah;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    private function user(Role $r): User
    {
        return User::factory()->role($r)->create();
    }

    private function product(array $over = []): Product
    {
        return Product::create($over + ['title_latin' => 'Fathul Qarib', 'weight_grams' => 420, 'price_egp' => 8500, 'price_regular' => 150000, 'price_reseller' => 135000]);
    }

    // ---------- Produk ----------

    public function test_admin_never_receives_purchase_price()
    {
        $this->product();
        $this->actingAs($this->user(Role::Admin))->get('/products')
            ->assertInertia(fn (Assert $p) => $p->where('items.data.0.price_regular', 150000)->missing('items.data.0.price_egp'));

        $this->actingAs($this->user(Role::Owner))->get('/products')
            ->assertInertia(fn (Assert $p) => $p->where('items.data.0.price_egp', 8500));
    }

    public function test_admin_cannot_change_prices_but_can_change_info()
    {
        $p = $this->product();
        $this->actingAs($this->user(Role::Admin))->post("/products/{$p->id}", [
            'title_latin' => 'Fathul Qarib (baru)', 'weight_grams' => 500,
            'price_regular' => 1, 'price_reseller' => 1, 'price_egp' => 1,
        ])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Fathul Qarib (baru)', $p->title_latin);
        $this->assertSame(500, $p->weight_grams);
        $this->assertSame(150000, $p->price_regular);
        $this->assertSame(8500, $p->price_egp);
    }

    public function test_owner_price_change_is_audited()
    {
        $p = $this->product();
        $this->actingAs($this->user(Role::Owner))->post("/products/{$p->id}", [
            'title_latin' => 'Fathul Qarib', 'weight_grams' => 420, 'price_egp' => 9000, 'price_regular' => 160000, 'price_reseller' => 140000,
        ])->assertSessionHasNoErrors();

        $log = DB::table('audit_logs')->where('action', 'product.price')->first();
        $this->assertNotNull($log);
        $this->assertSame(160000, json_decode($log->after, true)['price_regular']);
    }

    public function test_weight_is_required_and_positive()
    {
        $this->actingAs($this->user(Role::Owner))->post('/products', ['title_latin' => 'X', 'weight_grams' => 0, 'price_egp' => 0, 'price_regular' => 0, 'price_reseller' => 0])
            ->assertSessionHasErrors('weight_grams');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_sku_generated_automatically()
    {
        $this->actingAs($this->user(Role::Admin))->post('/products', ['title_latin' => 'Arbain', 'weight_grams' => 180])->assertSessionHasNoErrors();
        $this->assertMatchesRegularExpression('/^K\d{5}$/', Product::first()->sku);
    }

    public function test_only_owner_can_delete_and_used_product_is_protected()
    {
        $p = $this->product();
        $this->actingAs($this->user(Role::Admin))->delete("/products/{$p->id}")->assertForbidden();

        $c = Customer::create(['name' => 'A', 'type' => 'reguler']);
        $owner = $this->user(Role::Owner);
        $orderId = DB::table('orders')->insertGetId(['number' => 'T1', 'customer_id' => $c->id, 'channel' => 'whatsapp', 'payment_status' => 'paid', 'status' => 'selesai', 'subtotal' => 1, 'total' => 1, 'created_by' => $owner->id]);
        DB::table('order_items')->insert(['order_id' => $orderId, 'product_id' => $p->id, 'qty' => 1, 'unit_price' => 1, 'line_total' => 1, 'qty_to_buy' => 1]);

        $this->actingAs($owner)->delete("/products/{$p->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted($p);
    }

    public function test_photo_upload_is_private()
    {
        $p = $this->product();
        $owner = $this->user(Role::Owner);
        $this->actingAs($owner)->post("/products/{$p->id}", [
            'title_latin' => 'Fathul Qarib', 'weight_grams' => 420, 'price_egp' => 8500, 'price_regular' => 150000, 'price_reseller' => 135000,
            'photo' => UploadedFile::fake()->image('kitab.jpg', 300, 400),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->user(Role::Mesir))->get("/products/{$p->id}/photo")->assertOk();
        auth()->logout();
        $this->get("/products/{$p->id}/photo")->assertRedirect('/login');
        $this->assertStringNotContainsString('public', $p->refresh()->photo_path);
    }

    // ---------- Maktabah & Customer ----------

    public function test_mesir_can_view_but_not_edit_maktabah()
    {
        $m = Maktabah::create(['name' => 'Dar al-Salam']);
        $mesir = $this->user(Role::Mesir);
        $this->actingAs($mesir)->get('/maktabahs')->assertOk()->assertInertia(fn (Assert $p) => $p->where('canEdit', false));
        $this->actingAs($mesir)->post('/maktabahs', ['name' => 'Baru'])->assertForbidden();
        $this->actingAs($mesir)->put("/maktabahs/{$m->id}", ['name' => 'Ubah'])->assertForbidden();
    }

    public function test_maktabah_used_by_product_cannot_be_deleted()
    {
        $m = Maktabah::create(['name' => 'Dar al-Salam']);
        $this->product(['primary_maktabah_id' => $m->id]);
        $this->actingAs($this->user(Role::Owner))->delete("/maktabahs/{$m->id}")->assertSessionHasErrors('delete');
        $this->assertNotSoftDeleted($m);
    }

    public function test_customer_code_and_crud()
    {
        $admin = $this->user(Role::Admin);
        $this->actingAs($admin)->post('/customers', ['name' => 'Ahmad', 'type' => 'reseller'])->assertSessionHasNoErrors();
        $c = Customer::first();
        $this->assertSame('C00001', $c->code);
        $this->actingAs($admin)->delete("/customers/{$c->id}")->assertForbidden();
        $this->actingAs($this->user(Role::Gudang))->get('/customers')->assertForbidden();
    }

    // ---------- Import ----------

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('data.csv', $content);
    }

    public function test_product_import_preview_then_confirm()
    {
        Maktabah::create(['name' => 'Dar al-Salam']);
        $owner = $this->user(Role::Owner);
        $csv = "sku,judul_latin*,judul_arab,berat_gram*,maktabah_utama,harga_egp,harga_reguler,harga_reseller,stok_awal\n"
            ."K-1,Fathul Qarib,فتح القريب,420,Dar al-Salam,85.5,150.000,135000,7\n"
            .",Tanpa Berat,,,,,,,\n"
            .",Arbain,,180,Toko Hantu,,,,\n";

        $res = $this->actingAs($owner)->post('/imports', ['type' => 'products', 'file' => $this->csv($csv)]);
        $id = DB::table('imports')->value('id');
        $res->assertRedirect("/imports/{$id}");

        $this->assertDatabaseCount('products', 0); // pratinjau belum menyimpan

        $this->actingAs($owner)->get("/imports/{$id}")->assertInertia(fn (Assert $p) => $p
            ->where('import.rows_ok', 1)->where('import.rows_failed', 2)
            ->where('rows.1.errors.0', 'berat_gram kosong (wajib untuk kuota bagasi)'));

        $this->actingAs($owner)->post("/imports/{$id}/confirm")->assertRedirect('/products');

        $p = Product::firstWhere('sku', 'K-1');
        $this->assertSame('فتح القريب', $p->title_ar);
        $this->assertSame(8550, $p->price_egp);
        $this->assertSame(150000, $p->price_regular);
        $this->assertSame(7, (int) DB::table('product_stock')->where('product_id', $p->id)->value('qty'));
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'import_awal')->count());
        $this->assertDatabaseCount('products', 1);

        // Konfirmasi dua kali ditolak
        $this->actingAs($owner)->post("/imports/{$id}/confirm")->assertStatus(409);
    }

    public function test_reimport_updates_instead_of_duplicating_and_stock_once()
    {
        $owner = $this->user(Role::Owner);
        $csv = "sku,judul_latin,berat_gram,stok_awal\nK-9,Arbain,180,5\n";
        foreach ([1, 2] as $_) {
            $this->actingAs($owner)->post('/imports', ['type' => 'products', 'file' => $this->csv($csv)]);
            $id = DB::table('imports')->max('id');
            $this->actingAs($owner)->post("/imports/{$id}/confirm");
        }
        $this->assertDatabaseCount('products', 1);
        $this->assertSame(5, (int) DB::table('product_stock')->value('qty'));
    }

    public function test_admin_import_ignores_price_and_stock_columns()
    {
        $admin = $this->user(Role::Admin);
        $csv = "judul_latin,berat_gram,harga_reguler,stok_awal\nArbain,180,999,50\n";
        $this->actingAs($admin)->post('/imports', ['type' => 'products', 'file' => $this->csv($csv)]);
        $this->actingAs($admin)->post('/imports/'.DB::table('imports')->value('id').'/confirm');

        $p = Product::first();
        $this->assertSame(0, $p->price_regular);
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    public function test_admin_cannot_import_maktabah_or_see_others_import()
    {
        $admin = $this->user(Role::Admin);
        $this->actingAs($admin)->post('/imports', ['type' => 'maktabahs', 'file' => $this->csv("nama\nX\n")])->assertSessionHasErrors('type');

        $owner = $this->user(Role::Owner);
        $this->actingAs($owner)->post('/imports', ['type' => 'customers', 'file' => $this->csv("nama,tipe\nA,reseller\n")]);
        $id = DB::table('imports')->value('id');
        $this->actingAs($admin)->get("/imports/{$id}")->assertNotFound();
    }

    public function test_customer_import_semicolon_csv()
    {
        $owner = $this->user(Role::Owner);
        $this->actingAs($owner)->post('/imports', ['type' => 'customers', 'file' => $this->csv("nama;telepon;tipe\nAhmad;0812;Reseller\nBudi;;bukan\n")]);
        $id = DB::table('imports')->value('id');
        $this->actingAs($owner)->post("/imports/{$id}/confirm");

        $this->assertSame(1, Customer::count());
        $this->assertSame('reseller', Customer::first()->type);
    }
}
