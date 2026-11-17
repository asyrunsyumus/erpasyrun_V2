<?php

namespace Tests\Feature\Settings;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $this->actingAs(User::factory()->create())->get('/settings/profile')->assertOk();
    }

    public function test_name_can_be_updated()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/settings/profile', ['name' => 'Nama Baru'])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/settings/profile');

        $this->assertSame('Nama Baru', $user->refresh()->name);
    }

    public function test_staff_cannot_change_own_email_or_role()
    {
        $user = User::factory()->role(Role::Gudang)->create(['email' => 'gudang@contoh.com']);

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'X',
            'email' => 'lain@contoh.com',
            'role' => 'owner',
        ]);

        $user->refresh();
        $this->assertSame('gudang@contoh.com', $user->email);
        $this->assertSame(Role::Gudang, $user->role);
    }

    public function test_staff_cannot_delete_own_account()
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings/profile', ['password' => 'password'])->assertStatus(405);
        $this->assertNotNull($user->fresh());
    }
}
