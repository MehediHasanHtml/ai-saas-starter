<?php

namespace App\Http\Controllers\Api;

use App\Billing\Entitlements;
use App\Http\Controllers\Controller;
use App\Models\UsageRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UsageController extends Controller
{
    public function summary(Entitlements $entitlements): JsonResponse
    {
        $organization = $this->tenant();
        $since = now()->subDays(29)->startOfDay();

        $daily = UsageRecord::query()
            ->where('type', UsageRecord::TYPE_CHAT)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as day, SUM(quantity) as messages, SUM(input_tokens + output_tokens) as tokens')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->keyBy(fn ($row) => (string) $row->day);

        $series = collect(range(0, 29))->map(function ($i) use ($since, $daily) {
            $day = $since->copy()->addDays($i)->toDateString();

            return [
                'date' => $day,
                'messages' => (int) ($daily[$day]->messages ?? 0),
                'tokens' => (int) ($daily[$day]->tokens ?? 0),
            ];
        });

        $byModel = UsageRecord::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->select('provider', 'model', DB::raw('SUM(quantity) as requests'), DB::raw('SUM(input_tokens) as input_tokens'), DB::raw('SUM(output_tokens) as output_tokens'))
            ->groupBy('provider', 'model')
            ->orderByDesc('requests')
            ->get();

        return response()->json([
            'usage' => $entitlements->usage($organization),
            'daily' => $series,
            'by_model' => $byModel,
            'totals' => [
                'conversations' => $organization->conversations()->count(),
                'documents_ready' => $organization->documents()->where('status', 'ready')->count(),
            ],
        ]);
    }
}
