<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/orders')->assertRedirect('/login');
    }

    public function test_every_role_can_open_dashboard()
    {
        foreach (Role::cases() as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/dashboard')
                ->assertOk();
        }
    }

    public function test_only_owner_gets_financial_figures()
    {
        $this->actingAs(User::factory()->role(Role::Owner)->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $p) => $p->has('kpis')->has('series'));

        foreach ([Role::Admin, Role::Mesir, Role::Gudang] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/dashboard')
                ->assertInertia(fn (Assert $p) => $p->missing('kpis')->missing('series')->missing('top_products'));
        }
    }

    /** Matriks hak akses sesuai PRD "Siapa mengerjakan apa". */
    public function test_menu_access_matrix()
    {
        $matrix = [
            '/orders' => [Role::Owner, Role::Admin],
            '/customers' => [Role::Owner, Role::Admin],
            '/products' => [Role::Owner, Role::Admin],
            '/purchases' => [Role::Owner, Role::Mesir],
            '/baggage' => [Role::Owner],
            '/warehouse' => [Role::Gudang],
            '/reports' => [Role::Owner],
            '/expenses' => [Role::Owner],
            '/users' => [Role::Owner],
        ];

        $users = [];
        foreach (Role::cases() as $role) {
            $users[$role->value] = User::factory()->role($role)->create();
        }

        foreach ($matrix as $path => $allowed) {
            foreach (Role::cases() as $role) {
                $res = $this->actingAs($users[$role->value])->get($path);
                in_array($role, $allowed, true)
                    ? $res->assertOk()
                    : $res->assertForbidden();
            }
        }
    }

    public function test_menu_only_lists_allowed_modules()
    {
        $this->actingAs(User::factory()->role(Role::Mesir)->create())
            ->get('/dashboard')
            ->assertInertia(fn (Assert $p) => $p->where('nav', fn ($nav) => collect($nav)->pluck('key')->sort()->values()->all() === ['dashboard', 'maktabahs', 'purchases']));
    }

    public function test_inactive_user_is_logged_out_with_clear_message()
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => 'Akun Anda sudah dinonaktifkan. Hubungi Owner.']);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_log_in()
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed_first()
    {
        $user = User::factory()->withTemporaryPassword()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/settings/password');

        $this->actingAs($user)->put('/settings/password', [
            'current_password' => 'password',
            'password' => 'PasswordBaru123',
            'password_confirmation' => 'PasswordBaru123',
        ])->assertRedirect('/dashboard');

        $this->assertFalse($user->refresh()->must_change_password);
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_public_registration_is_disabled()
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.com', 'password' => 'Password123', 'password_confirmation' => 'Password123'])->assertNotFound();
    }

    public function test_security_headers_are_sent()
    {
        $res = $this->get('/login');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
    }
}
