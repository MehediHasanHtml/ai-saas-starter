<?php

namespace Tests;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a user that owns an organization and authenticate as them.
     *
     * @return array{0: User, 1: Organization}
     */
    protected function signIn(?string $plan = null, bool $superAdmin = false): array
    {
        $factory = User::factory()->withOrganization(plan: $plan);
        $user = ($superAdmin ? $factory->superAdmin() : $factory)->create()->fresh();

        Sanctum::actingAs($user);

        return [$user, $user->currentOrganization];
    }

    protected function tenantHeaders(Organization $organization): array
    {
        return ['X-Organization-Id' => (string) $organization->id, 'Accept' => 'application/json'];
    }
}
