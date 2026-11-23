<?php

namespace App\Http\Controllers\Admin;

use App\Billing\Plan;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Organization;
use App\Models\UsageRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide metrics. Tenant scopes are bypassed explicitly here.
 */
class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        $organizations = Organization::with('subscriptions')->get();

        $planCounts = collect(Plan::all())->mapWithKeys(fn (Plan $p) => [$p->key => 0])->all();
        $mrr = 0;

        foreach ($organizations as $org) {
            $plan = $org->plan();
            $planCounts[$plan->key] = ($planCounts[$plan->key] ?? 0) + 1;
            // Comped (override) plans don't generate revenue.
            if (! $org->plan_override) {
                $mrr += $plan->priceMonthly;
            }
        }

        $monthStart = now()->startOfMonth();

        return response()->json([
            'users' => User::count(),
            'users_new_30d' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'organizations' => $organizations->count(),
            'suspended' => $organizations->whereNotNull('suspended_at')->count(),
            'plans' => $planCounts,
            'mrr' => $mrr,
            'documents' => Document::withoutTenancy()->count(),
            'chunks' => DB::table('document_chunks')->count(),
            'messages_this_month' => (int) UsageRecord::withoutTenancy()
                ->where('type', UsageRecord::TYPE_CHAT)->where('created_at', '>=', $monthStart)->sum('quantity'),
            'tokens_this_month' => (int) UsageRecord::withoutTenancy()
                ->where('created_at', '>=', $monthStart)->sum(DB::raw('input_tokens + output_tokens')),
            'by_provider' => UsageRecord::withoutTenancy()
                ->where('type', UsageRecord::TYPE_CHAT)
                ->where('created_at', '>=', $monthStart)
                ->select('provider', DB::raw('SUM(quantity) as messages'), DB::raw('SUM(input_tokens + output_tokens) as tokens'))
                ->groupBy('provider')
                ->orderByDesc('messages')
                ->get(),
        ]);
    }

    public function usage(): JsonResponse
    {
        $since = now()->subDays(29)->startOfDay();

        $rows = UsageRecord::withoutTenancy()
            ->where('created_at', '>=', $since)
            ->selectRaw("DATE(created_at) as day, SUM(CASE WHEN type = 'chat' THEN quantity ELSE 0 END) as messages, SUM(input_tokens + output_tokens) as tokens, COUNT(DISTINCT organization_id) as active_orgs")
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($r) => (string) $r->day);

        $signups = User::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = collect(range(0, 29))->map(function ($i) use ($since, $rows, $signups) {
            $day = $since->copy()->addDays($i)->toDateString();

            return [
                'date' => $day,
                'messages' => (int) ($rows[$day]->messages ?? 0),
                'tokens' => (int) ($rows[$day]->tokens ?? 0),
                'active_orgs' => (int) ($rows[$day]->active_orgs ?? 0),
                'signups' => (int) ($signups[$day] ?? 0),
            ];
        });

        return response()->json(['data' => $series]);
    }
}
