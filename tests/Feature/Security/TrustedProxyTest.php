<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::middleware('web')->get('/__ip_probe', fn () => request()->ip());
    }

    public function test_spoofed_forwarded_for_from_untrusted_client_is_ignored(): void
    {
        $response = $this->call('GET', '/__ip_probe', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.7',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        $response->assertOk();
        $this->assertSame('203.0.113.7', $response->getContent());
        $this->assertNotSame('9.9.9.9', $response->getContent());
    }

    public function test_forwarded_for_from_the_loopback_proxy_is_honoured(): void
    {
        $response = $this->call('GET', '/__ip_probe', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_FOR' => '9.9.9.9',
        ]);

        $response->assertOk();
        $this->assertSame('9.9.9.9', $response->getContent());
    }
}
