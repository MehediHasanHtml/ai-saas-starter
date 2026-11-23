<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data:
 *   admin@example.com / password  — platform admin, owner of "Acme Inc" (Business)
 *   demo@example.com  / password  — member of Acme, owner of a Free workspace
 *   + a handful of organizations on each plan with 30 days of usage history
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // These accounts use the password "password". Never create them on a
        // live site unless explicitly asked to (SEED_DEMO_DATA=true).
        if (app()->isProduction() && ! filter_var(env('SEED_DEMO_DATA', false), FILTER_VALIDATE_BOOL)) {
            $this->command?->warn('Skipped demo data: APP_ENV=production. Set SEED_DEMO_DATA=true to seed anyway.');

            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Platform Admin', 'password' => 'password', 'email_verified_at' => now()],
        );
        $admin->forceFill(['is_super_admin' => true])->save();

        $acme = Organization::where('slug', 'acme-inc')->first()
            ?? $admin->createOrganization('Acme Inc');
        $acme->forceFill(['plan_override' => 'business'])->save();

        $demo = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo User', 'password' => 'password', 'email_verified_at' => now()],
        );
        if (! $demo->organizations()->exists()) {
            $demo->createOrganization("Demo's Workspace", personal: true);
        }
        $acme->members()->syncWithoutDetaching([$demo->id => ['role' => Role::Member->value]]);

        // Sample tenants for the admin dashboard.
        if (Organization::count() < 6) {
            foreach (['pro' => 3, 'free' => 4, 'business' => 1] as $plan => $count) {
                User::factory($count)->withOrganization(plan: $plan === 'free' ? null : $plan)->create();
            }
        }

        $this->seedUsage();
    }

    private function seedUsage(): void
    {
        if (UsageRecord::withoutTenancy()->exists()) {
            return;
        }

        $models = [['openai', 'gpt-4.1-mini'], ['anthropic', 'claude-sonnet-5-5'], ['gemini', 'gemini-2.5-flash'], ['ollama', 'llama3.2']];
        $rows = [];

        foreach (Organization::all() as $org) {
            $weight = match ($org->plan()->key) { 'business' => 25, 'pro' => 10, default => 2 };
            $userId = $org->owner_id;

            for ($day = 29; $day >= 0; $day--) {
                $n = random_int(0, $weight);
                for ($i = 0; $i < $n; $i++) {
                    [$provider, $model] = $models[array_rand($models)];
                    $rows[] = [
                        'organization_id' => $org->id,
                        'user_id' => $userId,
                        'type' => 'chat',
                        'provider' => $provider,
                        'model' => $model,
                        'input_tokens' => random_int(300, 4000),
                        'output_tokens' => random_int(80, 900),
                        'quantity' => 1,
                        'created_at' => now()->subDays($day)->setTime(random_int(8, 20), random_int(0, 59)),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            UsageRecord::withoutTenancy()->insert($chunk);
        }
    }
}
