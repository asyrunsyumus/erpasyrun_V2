<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Customer;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    private function enable2fa(User $u): string
    {
        $secret = (new Google2FA)->generateSecretKey(32);
        $u->forceFill(['two_factor_secret' => encrypt($secret), 'two_factor_confirmed_at' => now()])->save();

        return $secret;
    }

    private function code(string $secret, int $offset = 0): string
    {
        return (new Google2FA)->getCurrentOtp($secret) && $offset === 0
            ? (new Google2FA)->getCurrentOtp($secret)
            : (new Google2FA)->oathTotp($secret, (int) floor(time() / 30) + $offset);
    }

    public function test_owner_without_2fa_is_forced_to_set_it_up()
    {
        config(['erp.require_owner_2fa' => true]);
        $owner = User::factory()->role(Role::Owner)->create();
        $this->actingAs($owner)->get('/dashboard')->assertRedirect('/settings/two-factor');
        $this->actingAs($owner)->get('/reports')->assertRedirect('/settings/two-factor');
        $this->actingAs($owner)->get('/settings/two-factor')->assertOk();

        // Staf lain tidak dipaksa
        $this->actingAs(User::factory()->role(Role::Admin)->create())->get('/dashboard')->assertOk();
    }

    public function test_setup_flow_enables_2fa_and_gives_recovery_codes()
    {
        config(['erp.require_owner_2fa' => true]);
        $owner = User::factory()->role(Role::Owner)->create();
        $this->actingAs($owner)->post('/settings/two-factor/start');
        $secret = session('2fa.setup');
        $this->assertNotNull($secret);

        $this->actingAs($owner)->post('/settings/two-factor/confirm', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->actingAs($owner)->post('/settings/two-factor/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertSessionHasNoErrors();

        $owner->refresh();
        $this->assertNotNull($owner->two_factor_confirmed_at);
        $this->assertNotSame($secret, $owner->two_factor_secret); // tersimpan terenkripsi
        $this->assertCount(8, session('2fa.codes'));
        $this->actingAs($owner)->get('/dashboard')->assertOk();

        // Owner tidak boleh mematikan 2FA
        $this->actingAs($owner)->delete('/settings/two-factor', ['password' => 'password'])->assertForbidden();
    }

    public function test_login_requires_code_when_2fa_enabled()
    {
        $u = User::factory()->role(Role::Owner)->create(['email' => 'o@x.com']);
        $secret = $this->enable2fa($u);

        $this->post('/login', ['email' => 'o@x.com', 'password' => 'password'])->assertRedirect('/two-factor-challenge');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');

        $this->post('/two-factor-challenge', ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();

        $this->post('/two-factor-challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
    }

    public function test_same_code_cannot_be_reused()
    {
        $u = User::factory()->role(Role::Owner)->create(['email' => 'o@x.com']);
        $secret = $this->enable2fa($u);
        $code = (new Google2FA)->getCurrentOtp($secret);

        $this->post('/login', ['email' => 'o@x.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertRedirect('/dashboard');
        auth()->logout();

        $this->post('/login', ['email' => 'o@x.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once()
    {
        $u = User::factory()->role(Role::Owner)->create(['email' => 'o@x.com']);
        $this->enable2fa($u);
        $codes = app(TwoFactor::class)->newRecoveryCodes($u);

        $this->post('/login', ['email' => 'o@x.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertRedirect('/dashboard');
        auth()->logout();

        $this->post('/login', ['email' => 'o@x.com', 'password' => 'password']);
        $this->post('/two-factor-challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
    }

    public function test_challenge_locks_after_too_many_wrong_codes()
    {
        $u = User::factory()->role(Role::Owner)->create(['email' => 'o@x.com']);
        $this->enable2fa($u);
        $this->post('/login', ['email' => 'o@x.com', 'password' => 'password']);
        foreach (range(1, 5) as $_) {
            $this->post('/two-factor-challenge', ['code' => '000000']);
        }
        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors(['code' => 'Terlalu banyak kode salah. Coba lagi dalam 15 menit.']);
    }

    public function test_challenge_without_password_step_is_rejected()
    {
        $this->post('/two-factor-challenge', ['code' => '123456'])->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_secret_never_sent_to_browser()
    {
        $u = User::factory()->role(Role::Admin)->create();
        $secret = $this->enable2fa($u);
        $html = $this->actingAs($u)->get('/dashboard')->getContent();
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringNotContainsString('two_factor_secret', $html);
    }

    // ---------------- Cadangan ----------------

    public function test_backup_requires_owner_and_password()
    {
        Customer::create(['name' => "O'Brien", 'type' => 'reguler']);
        $owner = User::factory()->role(Role::Owner)->create();

        $this->actingAs(User::factory()->role(Role::Admin)->create())->post('/backup', ['password' => 'password'])->assertForbidden();
        $this->actingAs($owner)->post('/backup', ['password' => 'salah'])->assertSessionHasErrors('password');

        $res = $this->actingAs($owner)->post('/backup', ['password' => 'password']);
        $res->assertOk();
        $sql = $res->streamedContent();
        $this->assertStringContainsString('INSERT INTO "customers"', $sql);
        $this->assertStringContainsString("'O''Brien'", $sql); // tanda kutip di-escape
        $this->assertStringNotContainsString('INSERT INTO "sessions"', $sql);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.download']);
    }
}
