<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => ['is_super_admin' => true]);
    }

    /** Create the user together with an owned organization. */
    public function withOrganization(?string $name = null, ?string $plan = null): static
    {
        return $this->afterCreating(function (User $user) use ($name, $plan) {
            $org = $user->createOrganization($name ?? "{$user->name}'s Workspace");
            if ($plan) {
                $org->forceFill(['plan_override' => $plan])->save();
            }
        });
    }
}
