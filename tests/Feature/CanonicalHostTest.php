<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalHostTest extends TestCase
{
    use RefreshDatabase;

    private function production(): void
    {
        $this->app['env'] = 'production';
        config(['erp.canonical_host' => 'asyrunsyumus.com']);
    }

    public function test_other_hosts_redirect_to_canonical_in_production()
    {
        $this->production();
        $this->get('http://close-snow-sheep-75.hostingersite.com/login?x=1')->assertRedirect('https://asyrunsyumus.com/login?x=1')->assertStatus(301);
        $this->get('http://www.asyrunsyumus.com/login')->assertRedirect('https://asyrunsyumus.com/login');
    }

    public function test_canonical_host_and_posts_are_not_redirected()
    {
        $this->production();
        $this->get('https://asyrunsyumus.com/login')->assertOk();
        $this->post('http://close-snow-sheep-75.hostingersite.com/_deploy', ['token' => 'x'])->assertNotFound();
    }

    public function test_no_redirect_outside_production()
    {
        config(['erp.canonical_host' => 'asyrunsyumus.com']);
        $this->get('http://localhost/login')->assertOk();
    }
}
