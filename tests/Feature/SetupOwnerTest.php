<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupOwnerTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('app/setup.token');
        $this->token = str_repeat('k', 48);
        file_put_contents($this->file, $this->token);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function payload(): array
    {
        return ['name' => 'Owner', 'email' => 'owner@contoh.com', 'password' => 'RahasiaKuat123', 'password_confirmation' => 'RahasiaKuat123'];
    }

    public function test_wrong_token_is_hidden()
    {
        $this->get('/setup/'.str_repeat('z', 48))->assertNotFound();
        $this->post('/setup/'.str_repeat('z', 48), $this->payload())->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_creates_owner_once_and_closes()
    {
        $this->get('/setup/'.$this->token)->assertOk();

        $this->post('/setup/'.$this->token, $this->payload())->assertRedirect('/dashboard');

        $owner = User::firstWhere('email', 'owner@contoh.com');
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertAuthenticatedAs($owner);
        $this->assertFileDoesNotExist($this->file);

        auth()->logout();
        file_put_contents($this->file, $this->token);
        $this->get('/setup/'.$this->token)->assertNotFound();
    }

    public function test_closed_when_any_user_exists_even_with_token()
    {
        User::factory()->create();
        $this->get('/setup/'.$this->token)->assertNotFound();
    }

    public function test_weak_password_rejected()
    {
        $this->post('/setup/'.$this->token, ['password' => 'lemah', 'password_confirmation' => 'lemah'] + $this->payload())
            ->assertSessionHasErrors('password');
        $this->assertDatabaseCount('users', 0);
    }
}
