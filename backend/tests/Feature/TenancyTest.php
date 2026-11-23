<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Document;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_data_is_isolated_between_organizations(): void
    {
        $other = User::factory()->withOrganization('Other Co')->create()->fresh();
        $otherOrg = $other->currentOrganization;

        $foreignDoc = app(TenantContext::class)->runAs($otherOrg, fn () => Document::create([
            'title' => 'Secret plan', 'filename' => 'secret.txt', 'path' => 'x/secret.txt', 'status' => 'ready',
        ]));

        [, $org] = $this->signIn();

        $this->getJson('/api/documents', $this->tenantHeaders($org))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        // Route-model binding is tenant-scoped: another tenant's id is a 404.
        $this->getJson("/api/documents/{$foreignDoc->id}", $this->tenantHeaders($org))->assertNotFound();
        $this->deleteJson("/api/documents/{$foreignDoc->id}", [], $this->tenantHeaders($org))->assertNotFound();
        $this->assertDatabaseHas('documents', ['id' => $foreignDoc->id]);
    }

    public function test_cannot_select_an_organization_you_are_not_a_member_of(): void
    {
        $other = User::factory()->withOrganization()->create()->fresh();
        $this->signIn();

        $this->getJson('/api/documents', $this->tenantHeaders($other->currentOrganization))->assertForbidden();
    }

    public function test_scope_fails_closed_without_a_tenant(): void
    {
        $user = User::factory()->withOrganization()->create()->fresh();
        app(TenantContext::class)->runAs($user->currentOrganization, fn () => Conversation::create([
            'user_id' => $user->id, 'provider' => 'fake', 'model' => 'demo-echo',
        ]));

        $this->assertSame(0, Conversation::count());
        $this->assertSame(1, Conversation::withoutTenancy()->count());
    }

    public function test_members_cannot_perform_admin_actions(): void
    {
        $owner = User::factory()->withOrganization('Team')->create()->fresh();
        $org = $owner->currentOrganization;

        [$member] = $this->signIn();
        $org->members()->attach($member->id, ['role' => 'member']);

        $this->patchJson('/api/organization', ['name' => 'Hacked'], $this->tenantHeaders($org))->assertForbidden();
        $this->postJson('/api/invitations', ['email' => 'x@example.com'], $this->tenantHeaders($org))->assertForbidden();
    }

    public function test_invitation_flow(): void
    {
        [$owner, $org] = $this->signIn('pro');

        $url = $this->postJson('/api/invitations', ['email' => 'new@example.com', 'role' => 'admin'], $this->tenantHeaders($org))
            ->assertCreated()
            ->json('accept_url');
        $token = basename($url);

        $invitee = User::factory()->withOrganization()->create(['email' => 'new@example.com']);
        $this->actingAs($invitee, 'sanctum');

        $this->postJson("/api/invitations/{$token}/accept")->assertOk()->assertJsonPath('data.role', 'admin');
        $this->assertTrue($invitee->fresh()->belongsToOrganization($org));
    }
}
