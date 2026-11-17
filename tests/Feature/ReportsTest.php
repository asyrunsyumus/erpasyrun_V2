<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $admin;

    private Product $p;

    private Customer $reseller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->role(Role::Owner)->create();
        $this->admin = User::factory()->role(Role::Admin)->create();
        $this->p = Product::create(['title_latin' => 'Fathul Qarib', 'weight_grams' => 400, 'price_regular' => 150000, 'price_reseller' => 100000]);
        app(StockLedger::class)->move($this->p->id, 20, 'import_awal', null, 60000);
        $this->reseller = Customer::create(['name' => 'Toko =HACK()', 'type' => 'reseller']);
    }

    private function order(string $pay = 'paid', int $qty = 2, int $discount = 20000): Order
    {
        $this->actingAs($this->admin)->post('/orders', [
            'customer_id' => $this->reseller->id, 'channel' => 'whatsapp', 'payment_status' => $pay, 'discount' => $discount,
            'items' => [['product_id' => $this->p->id, 'qty' => $qty]],
        ])->assertSessionHasNoErrors();

        return Order::latest('id')->first();
    }

    public function test_only_owner()
    {
        foreach (['/reports', '/expenses', '/audit', '/shop-settings'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertForbidden();
            $this->actingAs($this->owner)->get($url)->assertOk();
        }
    }

    /** Masalah lama #3: tiga layar tiga angka → kini satu angka. */
    public function test_report_numbers_match_dashboard_and_margin_tab()
    {
        $this->order('paid', 2, 20000);   // net 180.000, modal 120.000
        $this->order('unpaid', 1, 0);     // net 100.000, modal 60.000
        $this->actingAs($this->owner)->post('/expenses', ['category' => 'operasional', 'amount' => 50000, 'spent_at' => now()->toDateString(), 'note' => 'listrik']);

        $dash = null;
        $this->actingAs($this->owner)->get('/dashboard')->assertInertia(function (Assert $p) use (&$dash) {
            $dash = $p->toArray()['props']['kpis'];
        });

        $this->actingAs($this->owner)->get('/reports?tab=margin')->assertInertia(fn (Assert $p) => $p
            ->where('summary.sales', 280000)
            ->where('summary.sales', $dash['sales']['value'])
            ->where('summary.cogs', 180000)
            ->where('summary.expenses', 50000)
            ->where('summary.profit', 280000 - 180000 - 50000)
            ->where('summary.profit', $dash['profit']['value'])
            ->where('data.rows.0.penjualan_bersih', 280000)
            ->where('data.rows.0.untung', 100000));
    }

    public function test_receivables_and_reseller_tabs()
    {
        $this->order('paid', 1, 0);
        $o = $this->order('unpaid', 2, 0);
        $this->actingAs($this->owner)->get('/reports?tab=hutang')->assertInertia(fn (Assert $p) => $p->has('data.rows', 1)->where('data.rows.0.order', $o->number)->where('summary.receivable', 200000));
        $this->actingAs($this->owner)->get('/reports?tab=reseller')->assertInertia(fn (Assert $p) => $p->where('data.rows.0.jumlah_order', 2)->where('data.rows.0.sudah_bayar', 100000)->where('data.rows.0.belum_bayar', 200000));
    }

    public function test_stock_value_tab()
    {
        $this->actingAs($this->owner)->get('/reports?tab=stok')->assertInertia(fn (Assert $p) => $p->where('data.rows.0.stok', 20)->where('data.rows.0.nilai_stok', 20 * 60000));
    }

    public function test_csv_export_is_safe_against_formula_injection()
    {
        $this->reseller->update(['name' => '=HYPERLINK("http://jahat")']);
        $this->order();
        $res = $this->actingAs($this->owner)->get('/reports/export?tab=penjualan');
        $res->assertOk();
        $csv = $res->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',"=HYPERLINK', $csv);
    }

    public function test_bagasi_expense_category_rejected_and_expense_crud_audited()
    {
        $this->actingAs($this->owner)->post('/expenses', ['category' => 'bagasi', 'amount' => 1, 'spent_at' => now()->toDateString(), 'note' => 'x'])->assertSessionHasErrors('category');
        $this->actingAs($this->owner)->post('/expenses', ['category' => 'lain', 'amount' => 1000, 'spent_at' => now()->toDateString(), 'note' => 'kemasan'])->assertSessionHasNoErrors();
        $id = DB::table('expenses')->value('id');
        $this->actingAs($this->owner)->delete("/expenses/{$id}")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action' => 'expense.delete']);
    }

    public function test_audit_log_page_lists_actions()
    {
        $this->actingAs($this->owner)->post('/expenses', ['category' => 'lain', 'amount' => 1000, 'spent_at' => now()->toDateString(), 'note' => 'kemasan']);
        $this->actingAs($this->owner)->get('/audit')->assertInertia(fn (Assert $p) => $p->where('items.data.0.label', 'Mencatat biaya'));
    }

    public function test_shop_settings_appear_on_invoice()
    {
        $this->actingAs($this->owner)->put('/shop-settings', ['shop_phone' => '0812', 'invoice_bank' => 'BSI 123', 'invoice_note' => 'Terima kasih'])->assertSessionHasNoErrors();
        $o = $this->order();
        $this->actingAs($this->admin)->get("/orders/{$o->id}/invoice")->assertInertia(fn (Assert $p) => $p->where('shop.bank', 'BSI 123')->where('shop.phone', '0812'));
    }
}
