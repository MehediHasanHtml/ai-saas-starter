<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_users_cannot_access_admin(): void
    {
        $this->signIn();

        $this->getJson('/api/admin/stats')->assertForbidden();
    }

    public function test_admin_sees_platform_stats_and_can_comp_a_plan(): void
    {
        $customer = User::factory()->withOrganization('Customer Co')->create()->fresh();
        $this->signIn(superAdmin: true);

        $this->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonPath('organizations', 2)
            ->assertJsonStructure(['users', 'mrr', 'plans' => ['free', 'pro', 'business'], 'by_provider']);

        $orgId = $customer->current_organization_id;

        $this->patchJson("/api/admin/organizations/{$orgId}", ['plan_override' => 'business'])
            ->assertOk()
            ->assertJsonPath('organization.plan', 'business');

        $this->patchJson("/api/admin/organizations/{$orgId}", ['suspended' => true])->assertOk();

        // Suspended tenants are locked out.
        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/documents')
            ->assertForbidden();
    }
}
