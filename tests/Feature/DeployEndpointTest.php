<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeployEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = storage_path('app/deploy.token');
        @unlink($this->file);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function test_hidden_without_token_file()
    {
        $this->post('/_deploy', ['token' => str_repeat('a', 48)])->assertNotFound();
    }

    public function test_wrong_token_is_rejected_and_file_kept()
    {
        file_put_contents($this->file, str_repeat('a', 48));
        $this->post('/_deploy', ['token' => str_repeat('b', 48)])->assertNotFound();
        $this->assertFileExists($this->file);
    }

    public function test_short_token_never_works()
    {
        file_put_contents($this->file, 'pendek');
        $this->post('/_deploy', ['token' => 'pendek'])->assertNotFound();
    }

    public function test_correct_token_runs_once()
    {
        $token = str_repeat('x', 48);
        file_put_contents($this->file, $token);

        $this->post('/_deploy', ['token' => $token])->assertOk()->assertSee('migrate (exit 0)');
        $this->assertFileDoesNotExist($this->file);

        $this->post('/_deploy', ['token' => $token])->assertNotFound();
    }
}
