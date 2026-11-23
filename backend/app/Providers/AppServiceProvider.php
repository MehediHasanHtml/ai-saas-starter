<?php

namespace App\Providers;

use App\AI\AiManager;
use App\Models\Organization;
use App\Rag\PgVectorStore;
use App\Rag\VectorStore;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped = fresh per request / per queued job (Octane-safe).
        $this->app->scoped(TenantContext::class);

        $this->app->singleton(AiManager::class, fn ($app) => new AiManager($app));
        $this->app->bind(VectorStore::class, PgVectorStore::class);

        if (method_exists(Cashier::class, 'ignoreMigrations')) {
            Cashier::ignoreMigrations(); // we ship organization-keyed migrations
        }
    }

    public function boot(): void
    {
        // The Organization (team), not the User, is the Stripe customer.
        Cashier::useCustomerModel(Organization::class);

        // Per-organization AI rate limit that scales with the plan.
        RateLimiter::for('ai', function (Request $request) {
            $tenant = app(TenantContext::class)->get();
            $perMinute = $tenant?->plan()->limit('requests_per_minute') ?? 10;

            return Limit::perMinute($perMinute)->by('ai:'.($tenant?->id ?? $request->ip()));
        });
    }
}
