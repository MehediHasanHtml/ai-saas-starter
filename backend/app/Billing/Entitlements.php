<?php

namespace App\Billing;

use App\Exceptions\QuotaExceededException;
use App\Models\Organization;
use App\Models\UsageRecord;

/**
 * Single place that answers "is this organization allowed to do X?" based on
 * its plan. Controllers call the ensure*() guards; the dashboard calls usage().
 */
class Entitlements
{
    public function messagesThisMonth(Organization $org): int
    {
        return (int) $org->usageRecords()
            ->where('type', UsageRecord::TYPE_CHAT)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('quantity');
    }

    public function documentCount(Organization $org): int
    {
        return $org->documents()->count();
    }

    public function storageBytes(Organization $org): int
    {
        return (int) $org->documents()->sum('size_bytes');
    }

    public function memberCount(Organization $org): int
    {
        return $org->members()->count() + $org->invitations()->where('expires_at', '>', now())->count();
    }

    public function ensureCanSendMessage(Organization $org): void
    {
        $limit = $org->plan()->limit('messages_per_month');

        if ($limit !== null && $this->messagesThisMonth($org) >= $limit) {
            throw new QuotaExceededException(
                "You've used all {$limit} AI messages included in your plan this month. Upgrade to keep chatting.",
                'messages_per_month',
            );
        }
    }

    public function ensureCanUploadDocument(Organization $org, int $sizeBytes): void
    {
        $plan = $org->plan();

        $maxFileMb = $plan->limit('max_file_mb');
        if ($maxFileMb !== null && $sizeBytes > $maxFileMb * 1024 * 1024) {
            throw new QuotaExceededException("Files on the {$plan->name} plan can be up to {$maxFileMb} MB.", 'max_file_mb');
        }

        $maxDocs = $plan->limit('documents');
        if ($maxDocs !== null && $this->documentCount($org) >= $maxDocs) {
            throw new QuotaExceededException("The {$plan->name} plan includes {$maxDocs} documents. Upgrade to add more.", 'documents');
        }

        $storageMb = $plan->limit('storage_mb');
        if ($storageMb !== null && $this->storageBytes($org) + $sizeBytes > $storageMb * 1024 * 1024) {
            throw new QuotaExceededException("This upload would exceed your {$storageMb} MB storage limit.", 'storage_mb');
        }
    }

    public function ensureCanAddMember(Organization $org): void
    {
        $plan = $org->plan();
        $limit = $plan->limit('members');

        if ($limit !== null && $this->memberCount($org) >= $limit) {
            throw new QuotaExceededException("The {$plan->name} plan includes up to {$limit} member(s).", 'members');
        }
    }

    public function ensureProviderAllowed(Organization $org, string $provider): void
    {
        $plan = $org->plan();

        if (! $plan->allowsProvider($provider)) {
            throw new QuotaExceededException("The {$provider} provider is not included in the {$plan->name} plan.", 'providers');
        }
    }

    public function usage(Organization $org): array
    {
        $plan = $org->plan();

        $meter = fn (int $used, ?int $limit) => [
            'used' => $used,
            'limit' => $limit,
            'percent' => $limit ? min(100, (int) round($used / max(1, $limit) * 100)) : 0,
        ];

        return [
            'plan' => $plan->toArray(),
            'period_start' => now()->startOfMonth()->toIso8601String(),
            'period_end' => now()->endOfMonth()->toIso8601String(),
            'messages' => $meter($this->messagesThisMonth($org), $plan->limit('messages_per_month')),
            'documents' => $meter($this->documentCount($org), $plan->limit('documents')),
            'storage_mb' => $meter((int) ceil($this->storageBytes($org) / 1024 / 1024), $plan->limit('storage_mb')),
            'members' => $meter($this->memberCount($org), $plan->limit('members')),
        ];
    }
}
