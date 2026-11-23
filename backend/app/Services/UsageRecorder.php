<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\UsageRecord;

class UsageRecorder
{
    public function record(
        Organization $organization,
        string $type,
        string $provider,
        string $model,
        int $inputTokens = 0,
        int $outputTokens = 0,
        int $quantity = 1,
        ?int $userId = null,
    ): UsageRecord {
        // Explicit organization_id so this also works from queued jobs.
        return UsageRecord::withoutTenancy()->create([
            'organization_id' => $organization->id,
            'user_id' => $userId,
            'type' => $type,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'quantity' => $quantity,
            'created_at' => now(),
        ]);
    }
}
