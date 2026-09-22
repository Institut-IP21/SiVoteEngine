<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Str;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ApiAuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = (string) Str::uuid();
        config(['app.api.authlist' => ['123123123']]);
        config(['app.api.admin_authlist' => []]);
    }

    /** @return array<string, string> */
    private function headers(string $token): array
    {
        return ['Authorization' => $token, 'Owner' => $this->owner];
    }

    public function test_exact_valid_token_is_accepted(): void
    {
        $this->withHeaders($this->headers('123123123'))
            ->getJson('/api/election')
            ->assertOk();
    }

    public function test_wrong_token_is_rejected(): void
    {
        $this->withHeaders($this->headers('123123124'))
            ->getJson('/api/election')
            ->assertStatus(401);
    }

    /** @return array<string, array{0: string}> */
    public static function looseVariants(): array
    {
        return [
            'trailing .0'   => ['123123123.0'],
            'exponent e0'   => ['123123123e0'],
            'exponent E8'   => ['1.23123123E8'],
            'leading zero'  => ['0123123123'],
            'leading space' => [' 123123123'],
        ];
    }

    #[DataProvider('looseVariants')]
    public function test_loose_numeric_variants_are_rejected(string $token): void
    {
        $this->withHeaders($this->headers($token))
            ->getJson('/api/election')
            ->assertStatus(401);
    }

    public function test_empty_token_is_rejected_even_when_the_list_contains_an_empty_entry(): void
    {
        config(['app.api.authlist' => ['']]);

        $this->withHeaders(['Owner' => $this->owner])
            ->getJson('/api/election')
            ->assertStatus(401);
    }

    public function test_missing_owner_header_is_403(): void
    {
        $this->withHeaders(['Authorization' => '123123123'])
            ->getJson('/api/election')
            ->assertStatus(403)
            ->assertJson(['error' => 'No owner.']);
    }

    public function test_non_uuid_owner_header_is_rejected(): void
    {
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => 'not-a-uuid'])
            ->getJson('/api/election')
            ->assertStatus(403)
            ->assertJson(['error' => 'Invalid owner.']);
    }

    public function test_admin_route_rejects_a_tenant_only_token(): void
    {
        config(['app.api.authlist' => ['tenant-token']]);
        config(['app.api.admin_authlist' => ['admin-secret']]);

        $this->withHeaders(['Authorization' => 'tenant-token', 'Owner' => $this->owner])
            ->getJson('/api/admin/stats')
            ->assertStatus(403)
            ->assertJson(['error' => 'Admin token required.']);
    }

    public function test_admin_route_accepts_the_admin_token(): void
    {
        config(['app.api.authlist' => ['tenant-token']]);
        config(['app.api.admin_authlist' => ['admin-secret']]);

        $this->withHeaders(['Authorization' => 'admin-secret', 'Owner' => $this->owner])
            ->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_list_falls_back_to_tenant_list_when_unset(): void
    {
        $this->withHeaders(['Authorization' => '123123123', 'Owner' => $this->owner])
            ->getJson('/api/admin/stats')
            ->assertOk();
    }
}
