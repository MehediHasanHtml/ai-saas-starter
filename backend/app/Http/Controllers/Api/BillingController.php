<?php

namespace App\Http\Controllers\Api;

use App\Billing\Entitlements;
use App\Billing\Plan;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Stripe billing through Laravel Cashier. The Organization is the customer.
 * Stripe webhooks are handled by Cashier at POST /stripe/webhook, which keeps
 * the `subscriptions` table in sync; Organization::plan() reads from it.
 */
class BillingController extends Controller
{
    public function plans(): JsonResponse
    {
        return response()->json(['data' => array_map(fn (Plan $p) => $p->toArray(), Plan::all())]);
    }

    public function show(Entitlements $entitlements): JsonResponse
    {
        $organization = $this->tenant();
        $subscription = $organization->subscription('default');

        return response()->json([
            'plan' => $organization->plan()->toArray(),
            'plans' => array_map(fn (Plan $p) => $p->toArray(), Plan::all()),
            'usage' => $entitlements->usage($organization),
            'stripe_enabled' => $this->stripeEnabled(),
            'override' => $organization->plan_override,
            'subscription' => $subscription ? [
                'status' => $subscription->stripe_status,
                'on_grace_period' => $subscription->onGracePeriod(),
                'ends_at' => $subscription->ends_at,
                'card' => $organization->pm_last_four ? "{$organization->pm_type} •••• {$organization->pm_last_four}" : null,
            ] : null,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $data = $request->validate(['plan' => ['required', Rule::in(array_keys(config('plans.plans')))]]);
        abort_unless($this->stripeEnabled(), 422, 'Stripe is not configured. Set STRIPE_KEY, STRIPE_SECRET and plan price ids.');

        $organization = $this->tenant();
        $plan = Plan::find($data['plan']);
        $subscription = $organization->subscription('default');
        $frontend = rtrim(config('saas.frontend_url'), '/');

        // Downgrade to Free = cancel at period end.
        if ($plan->isFree()) {
            $subscription?->valid() && $subscription->cancel();

            return response()->json(['message' => 'Your subscription will end at the close of the billing period.']);
        }

        abort_unless($plan->stripePrice, 422, "No Stripe price configured for the {$plan->name} plan.");

        // Already subscribed: swap price in place (prorated).
        if ($subscription && $subscription->valid()) {
            $subscription->onGracePeriod() && $subscription->resume();
            $subscription->swap($plan->stripePrice);

            return response()->json(['message' => "Switched to {$plan->name}."]);
        }

        $checkout = $organization->newSubscription('default', $plan->stripePrice)
            ->allowPromotionCodes()
            ->checkout([
                'success_url' => "{$frontend}/dashboard/billing?checkout=success",
                'cancel_url' => "{$frontend}/dashboard/billing?checkout=cancelled",
                'customer_update' => ['address' => 'auto', 'name' => 'auto'],
                'metadata' => ['organization_id' => $organization->id],
            ]);

        return response()->json(['url' => $checkout->asStripeCheckoutSession()->url]);
    }

    public function portal(): JsonResponse
    {
        abort_unless($this->stripeEnabled(), 422, 'Stripe is not configured.');

        $organization = $this->tenant();
        $organization->createOrGetStripeCustomer(['name' => $organization->name]);

        return response()->json([
            'url' => $organization->billingPortalUrl(rtrim(config('saas.frontend_url'), '/').'/dashboard/billing'),
        ]);
    }

    private function stripeEnabled(): bool
    {
        return filled(config('cashier.secret'));
    }
}
