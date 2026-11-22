<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\Maktabah;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class KosongkanDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_refuses_without_confirm_flag()
    {
        Maktabah::create(['name' => 'Dar al-Salam']);
        $this->artisan('erp:kosongkan-data')->assertExitCode(1);
        $this->assertSame(1, Maktabah::count());
    }

    public function test_empties_data_and_keeps_owner_and_settings()
    {
        $owner = User::factory()->role(Role::Owner)->create();
        User::factory()->role(Role::Admin)->create();
        User::factory()->role(Role::Mesir)->create();
        User::factory()->role(Role::Gudang)->create();
        $m = Maktabah::create(['name' => 'Dar al-Salam']);
        Product::create(['title_latin' => 'Arbain', 'primary_maktabah_id' => $m->id, 'price_egp' => 1, 'price_regular' => 1, 'price_reseller' => 1]);
        Customer::create(['name' => 'Ahmad', 'type' => 'reguler']);
        DB::table('settings')->insert(['key' => 'default_rate', 'value' => '3125000']);

        $this->artisan('erp:kosongkan-data', ['--confirm' => true])->assertExitCode(0);

        $this->assertSame(0, Maktabah::withTrashed()->count());
        $this->assertSame(0, Product::withTrashed()->count());
        $this->assertSame(0, Customer::withTrashed()->count());
        $this->assertSame([$owner->id], DB::table('users')->pluck('id')->all());
        $this->assertSame('3125000', DB::table('settings')->where('key', 'default_rate')->value('value'));
    }
}
