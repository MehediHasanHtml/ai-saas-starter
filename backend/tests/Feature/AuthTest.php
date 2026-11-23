<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_user_workspace_and_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret-password',
        ]);

        $response->assertCreated()->assertJsonStructure(['token', 'user' => ['id', 'email', 'current_organization_id']]);

        $user = User::where('email', 'ada@example.com')->first();
        $this->assertNotNull($user->current_organization_id);
        $this->assertSame('owner', $user->organizations()->first()->pivot->role);
        $this->assertSame('free', $user->currentOrganization->plan()->key);
    }

    public function test_login_returns_token_and_rejects_bad_password(): void
    {
        $user = User::factory()->withOrganization()->create(['email' => 'grace@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'grace@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();

        $token = $this->postJson('/api/auth/login', ['email' => 'grace@example.com', 'password' => 'password'])
            ->assertOk()
            ->json('token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonCount(1, 'organizations');
    }

    public function test_guests_cannot_reach_tenant_routes(): void
    {
        $this->getJson('/api/documents')->assertUnauthorized();
    }
}
