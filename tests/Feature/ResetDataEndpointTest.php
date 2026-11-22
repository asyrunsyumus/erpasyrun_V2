<?php

namespace Tests\Feature;

use App\Models\Maktabah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResetDataEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_endpoint_is_hidden_without_valid_token()
    {
        Maktabah::create(['name' => 'Dar al-Salam']);
        $this->post('/_reset', ['token' => 'salah'])->assertNotFound();
        $this->assertSame(1, Maktabah::count());
    }
}
